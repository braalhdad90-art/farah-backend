<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Favorite;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryServicesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_services_list_returns_proper_structure(): void
    {
        $city = City::firstOrCreate(['name' => 'غزة']);
        $category = Category::create([
            'name' => 'صالات',
            'slug' => 'wedding-halls',
            'status' => 'active',
        ]);

        $provider = User::factory()->create([
            'user_type' => 'provider',
            'city_id' => $city->id,
        ]);
        $this->approveProvider($provider);

        $service = Service::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'city_id' => $city->id,
            'title' => 'صالة أورنا',
            'description' => 'صالة أفراح مجهزة بالكامل',
            'price' => 1400.00,
            'currency' => 'ILS',
            'rating_avg' => 4.8,
            'reviews_count' => 24,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/services?category_id={$category->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'category' => ['id', 'name', 'slug'],
            'available_sorts',
            'active_sort',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'price',
                    'currency',
                    'rating_avg',
                    'reviews_count',
                    'location',
                    'city',
                    'is_favorited',
                    'button_text',
                    'provider',
                    'category',
                ]
            ],
            'pagination',
        ]);

        $this->assertEquals('صالات', $response->json('category.name'));
        $this->assertEquals('صالة أورنا', $response->json('data.0.title'));
        $this->assertEquals(1400.00, $response->json('data.0.price'));
        $this->assertEquals('تفاصيل الصالة', $response->json('data.0.button_text'));
    }

    public function test_services_can_be_filtered_by_search_and_sorted_by_price(): void
    {
        $category = Category::create([
            'name' => 'صالات',
            'slug' => 'halls',
            'status' => 'active',
        ]);

        $provider = User::factory()->create(['user_type' => 'provider']);
        $this->approveProvider($provider);

        Service::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'title' => 'صالة أورنا',
            'price' => 1400.00,
            'rating_avg' => 4.5,
            'status' => 'active',
        ]);

        Service::create([
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'title' => 'صالة هابي نايت',
            'price' => 1000.00,
            'rating_avg' => 5.0,
            'status' => 'active',
        ]);

        // الفرز بالأقل سعراً
        $response = $this->getJson("/api/services?category_id={$category->id}&sort_by=price_low");
        $response->assertStatus(200);
        $this->assertEquals('صالة هابي نايت', $response->json('data.0.title'));
        $this->assertEquals(1000.00, $response->json('data.0.price'));

        // البحث بكلمة 'اورنا'
        $searchResponse = $this->getJson("/api/services?search=اورنا");
        $searchResponse->assertStatus(200);
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertEquals('صالة أورنا', $searchResponse->json('data.0.title'));
    }

    public function test_favorite_toggle_and_listing_works_correctly(): void
    {
        $user = User::factory()->create();
        $provider = User::factory()->create(['user_type' => 'provider']);
        $this->approveProvider($provider);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'صالة أورنا',
            'price' => 1400.00,
            'status' => 'active',
        ]);

        // 1. إضافة إلى المفضلة
        $toggleAddResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/favorites/toggle/{$service->id}");

        $toggleAddResponse->assertStatus(200);
        $toggleAddResponse->assertJson([
            'status' => true,
            'is_favorited' => true,
        ]);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);

        // 2. التحقق من ظهور حالة is_favorited = true في قائمة الخدمات
        $serviceListResponse = $this->actingAs($user, 'sanctum')->getJson('/api/services');
        $this->assertTrue($serviceListResponse->json('data.0.is_favorited'));

        // 3. جلب قائمة المفضلة
        $favListResponse = $this->actingAs($user, 'sanctum')->getJson('/api/favorites');
        $favListResponse->assertStatus(200);
        $favListResponse->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'service_id', 'title', 'price', 'is_favorited']
            ]
        ]);
        $this->assertCount(1, $favListResponse->json('data'));

        // 4. إزالة من المفضلة عبر الـ Toggle مرة أخرى
        $toggleRemoveResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/favorites/toggle/{$service->id}");

        $toggleRemoveResponse->assertStatus(200);
        $toggleRemoveResponse->assertJson([
            'status' => true,
            'is_favorited' => false,
        ]);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_pending_provider_services_are_not_public_or_bookable(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Pending Provider',
            'status' => 'pending',
            'registration_status' => 'pending',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'Unapproved service',
            'price' => 100,
            'status' => 'active',
            'is_available' => true,
        ]);
        $customer = User::factory()->create(['user_type' => 'customer']);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/services/{$service->id}")
            ->assertNotFound();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/services/{$service->id}/bookings", [
                'booking_date' => now()->addDays(2)->toDateString(),
                'booking_time' => '17:00',
            ])
            ->assertNotFound();

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/services', [
                'title' => 'Another unapproved service',
                'price' => 100,
            ])
            ->assertForbidden();
    }

    private function approveProvider(User $provider): void
    {
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => $provider->name,
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);
    }
}
