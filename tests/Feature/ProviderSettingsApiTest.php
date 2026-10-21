<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProviderSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_saves_payout_account_and_sees_it_on_the_dashboard(): void
    {
        $provider = $this->provider();

        $this->actingAs($provider, 'sanctum')->putJson('/api/provider/payout-account', [
            'bank_name' => 'api bank',
            'account_number' => '1654987',
            'iban' => 'PS00TEST',
        ])->assertOk();

        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')
            ->assertJsonPath('data.provider.payout_account.bank_name', 'api bank')
            ->assertJsonPath('data.provider.payout_account.account_number', '1654987')
            ->assertJsonPath('data.provider.payout_account.iban', 'PS00TEST');

        $this->actingAs($provider, 'sanctum')->putJson('/api/provider/payout-account', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bank_name', 'account_number']);
    }

    public function test_store_profile_update_keeps_the_approval(): void
    {
        $provider = $this->provider();
        $city = City::create(['name' => 'Test city']);

        $this->actingAs($provider, 'sanctum')->postJson('/api/provider/profile', [
            'business_name' => 'New name',
            'description' => 'New description',
            'city_id' => $city->id,
            'address' => 'New address',
        ])->assertOk()
            ->assertJsonPath('data.profile.business_name', 'New name');

        $profile = $provider->providerProfile->fresh();
        $this->assertSame('approved', $profile->status);
        $this->assertSame('approved', $profile->registration_status);
        $this->assertSame('New description', $profile->description);
    }

    // فرح: شاشة "بيانات المتجر": الفئة ورقم السجل التجاري والشعار.
    public function test_store_profile_saves_category_register_number_and_logo(): void
    {
        Storage::fake('public');
        $provider = $this->provider();
        $city = City::create(['name' => 'Store city']);
        $category = Category::factory()->create(['name' => 'Flowers']);

        $this->actingAs($provider, 'sanctum')->post('/api/provider/profile', [
            'business_name' => 'Lavender',
            'description' => 'Flowers and gifts',
            'city_id' => $city->id,
            'address' => 'Gaza',
            'category_id' => $category->id,
            'commercial_register_number' => '562-889-114',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.profile.category.name', 'Flowers')
            ->assertJsonPath('data.profile.commercial_register_number', '562-889-114');

        $this->assertNotNull($provider->fresh()->avatar);
        Storage::disk('public')->assertExists($provider->fresh()->avatar);
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/onboarding')
            ->assertJsonPath('data.profile.commercial_register_number', '562-889-114');

        $inactive = Category::factory()->create(['status' => 'inactive']);
        $this->actingAs($provider, 'sanctum')->postJson('/api/provider/profile', [
            'business_name' => 'Lavender',
            'description' => 'Flowers and gifts',
            'city_id' => $city->id,
            'address' => 'Gaza',
            'category_id' => $inactive->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['category_id']);
    }

    // فرح: روابط الصور لازم تكون كاملة (مع الدومين) حتى يقدر التطبيق يعرضها.
    public function test_uploaded_images_return_full_urls(): void
    {
        // Keep the real public URL; the old code ignored it and used the default "local" disk.
        Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        config(['filesystems.default' => 'local']);
        $provider = $this->provider();
        $city = City::create(['name' => 'Url city']);

        $profile = $this->actingAs($provider, 'sanctum')->post('/api/provider/profile', [
            'business_name' => 'Store',
            'description' => 'Desc',
            'city_id' => $city->id,
            'address' => 'Gaza',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.profile');

        $this->assertMatchesRegularExpression('#^https?://.+/provider-covers/#', $profile['cover_image']);
        $this->assertMatchesRegularExpression('#^https?://.+/avatars/#', $profile['logo']);

        $avatar = $this->actingAs($provider, 'sanctum')->post('/api/profile/update', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.avatar');
        $this->assertMatchesRegularExpression('#^https?://.+/avatars/#', $avatar);

        $dashboard = $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')->json('data.provider');
        $this->assertMatchesRegularExpression('#^https?://#', $dashboard['avatar']);
        $this->assertMatchesRegularExpression('#^https?://#', $dashboard['cover_image']);
    }

    // فرح: العنوان في "تعديل الملف الشخصي" يُحفظ ويرجع، ولا يغيّر عنوان المتجر.
    public function test_profile_update_saves_the_personal_address(): void
    {
        $provider = $this->provider();

        $this->actingAs($provider, 'sanctum')->postJson('/api/profile/update', [
            'name' => 'Lavender owner',
            'region' => 'Gaza - Al Wehda',
        ])->assertOk()
            ->assertJsonPath('data.region', 'Gaza - Al Wehda');

        $this->actingAs($provider, 'sanctum')->getJson('/api/profile')
            ->assertJsonPath('data.region', 'Gaza - Al Wehda')
            ->assertJsonPath('data.name', 'Lavender owner');
        $this->assertDatabaseMissing('locations', ['user_id' => $provider->id, 'label' => 'business']);
    }

    // فرح: مركز المساعدة يعرض أسئلة المزود الظاهرة فقط، والأدمن يضيفها من اللوحة.
    public function test_faqs_are_filtered_by_audience(): void
    {
        \App\Models\Faq::query()->delete();
        \App\Models\Faq::create(['audience' => 'provider', 'question' => 'P?', 'answer' => 'p', 'sort_order' => 2]);
        \App\Models\Faq::create(['audience' => 'all', 'question' => 'A?', 'answer' => 'a', 'sort_order' => 1]);
        \App\Models\Faq::create(['audience' => 'customer', 'question' => 'C?', 'answer' => 'c']);
        \App\Models\Faq::create(['audience' => 'provider', 'question' => 'Hidden?', 'answer' => 'h', 'is_active' => false]);

        $this->getJson('/api/faqs?audience=provider')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.question', 'A?')
            ->assertJsonPath('data.1.question', 'P?');
    }

    public function test_external_booking_blocks_the_service_date(): void
    {
        $provider = $this->provider();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'title' => 'Hall']);
        $date = now()->addDays(5)->toDateString();

        $this->actingAs($provider, 'sanctum')->postJson('/api/provider/external-bookings', [
            'customer_name' => 'Walk-in',
            'service_id' => $service->id,
            'booking_date' => $date,
            'total_price' => 900,
            'deposit_amount' => 300,
            'payment_method' => 'نقدا',
        ])->assertCreated()
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.service_name', 'Hall')
            ->assertJsonPath('data.remaining_amount', 600);

        $this->assertDatabaseHas('service_unavailable_dates', ['service_id' => $service->id, 'reason' => 'حجز خارجي: Walk-in']);

        // The same day can't be booked twice.
        $this->actingAs($provider, 'sanctum')->postJson('/api/provider/external-bookings', [
            'customer_name' => 'Second',
            'service_id' => $service->id,
            'booking_date' => $date,
            'total_price' => 900,
        ])->assertStatus(422);

        // Another provider's service is refused.
        $other = Service::factory()->create(['provider_id' => User::factory()->create(['user_type' => 'provider'])->id]);
        $this->actingAs($provider, 'sanctum')->postJson('/api/provider/external-bookings', [
            'customer_name' => 'X',
            'service_id' => $other->id,
            'booking_date' => $date,
            'total_price' => 100,
        ])->assertStatus(422);
    }

    public function test_provider_is_reminded_before_and_after_the_review_deadline(): void
    {
        $provider = $this->provider();
        $customer = User::factory()->create(['name' => 'Sara']);
        $soon = Booking::factory()->create(['provider_id' => $provider->id, 'user_id' => $customer->id, 'workflow_status' => 'review_under_proof', 'expires_at' => now()->addHours(3)]);
        $late = Booking::factory()->create(['provider_id' => $provider->id, 'user_id' => $customer->id, 'workflow_status' => 'review_under_proof', 'expires_at' => now()->subHour()]);
        Booking::factory()->create(['provider_id' => $provider->id, 'workflow_status' => 'review_under_proof', 'expires_at' => now()->addHours(20)]);

        Artisan::call('bookings:payment-review-reminders');
        Artisan::call('bookings:payment-review-reminders');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $provider->id, 'type' => 'payment_review_reminder', 'resource_id' => $soon->id, 'title' => 'تبقى 3 ساعات لمراجعة اشعار دفع Sara']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $provider->id, 'type' => 'payment_review_overdue', 'resource_id' => $late->id]);
        $this->assertDatabaseCount('app_notifications', 2);
    }

    public function test_onboarding_saves_the_profit_account(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider']);

        $this->actingAs($provider, 'sanctum')->postJson('/api/provider/onboarding', [
            'business_name' => 'Store',
            'category_id' => Category::factory()->create()->id,
            'description' => 'Desc',
            'city_id' => City::create(['name' => 'Onboarding city'])->id,
            'address' => 'Street',
            'profit_account' => '998877',
        ])->assertOk();

        $this->assertSame('998877', $provider->fresh()->providerProfile->payout_account_number);
    }

    private function provider(): User
    {
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Store',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);

        return $provider;
    }
}
