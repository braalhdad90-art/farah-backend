<?php

namespace Tests\Feature;

use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderApprovedNotification;
use App\Notifications\ProviderRejectedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// فرح: إيميل ترحيب بعد التسجيل، وإيميل اعتماد/رفض المزود، وفشل الإرسال ما بيوقف شي.
class AccountEmailsTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $type): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/register', [
            'name' => 'New '.$type,
            'email' => $type.'@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => $type,
        ]);
    }

    private function mailText(object $notification, User $user): string
    {
        $mail = $notification->toMail($user);

        return $mail->subject.' '.implode(' ', $mail->introLines);
    }

    public function test_welcome_email_differs_for_customer_and_provider(): void
    {
        Notification::fake();

        $this->register('customer')->assertCreated();
        $this->register('provider')->assertCreated();

        $customer = User::where('email', 'customer@test.com')->first();
        $provider = User::where('email', 'provider@test.com')->first();
        Notification::assertSentTo($customer, WelcomeNotification::class,
            fn ($n) => str_contains($this->mailText($n, $customer), 'ادفع العربون'));
        Notification::assertSentTo($provider, WelcomeNotification::class,
            fn ($n) => str_contains($this->mailText($n, $provider), 'فترتك المجانية (3 أشهر)'));
    }

    public function test_registration_works_when_the_mail_service_is_down(): void
    {
        config(['mail.default' => 'brevo', 'mail.mailers.brevo.key' => 'key']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'down'], 500)]);

        $this->register('customer')->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'customer@test.com']);
    }

    public function test_provider_gets_approval_and_rejection_emails(): void
    {
        Notification::fake();
        Role::findOrCreate('system.admin', 'web');
        $admin = User::factory()->create(['user_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('system.admin');

        $approved = User::factory()->create(['user_type' => 'provider', 'status' => 'inactive']);
        $approvedProfile = ProviderProfile::create(['user_id' => $approved->id, 'business_name' => 'A', 'status' => 'pending', 'registration_status' => 'pending', 'submitted_at' => now()]);
        $this->actingAs($admin)->post(route('admin.provider.approve', $approvedProfile))->assertRedirect();
        Notification::assertSentTo($approved, ProviderApprovedNotification::class,
            fn ($n) => str_contains($this->mailText($n, $approved), '(3 أشهر)'));

        $rejected = User::factory()->create(['user_type' => 'provider', 'status' => 'inactive']);
        $rejectedProfile = ProviderProfile::create(['user_id' => $rejected->id, 'business_name' => 'B', 'status' => 'pending', 'registration_status' => 'pending', 'submitted_at' => now()]);
        $this->actingAs($admin)->post(route('admin.provider.reject', $rejectedProfile), ['reason' => 'صورة الهوية غير واضحة'])->assertRedirect();
        Notification::assertSentTo($rejected, ProviderRejectedNotification::class,
            fn ($n) => str_contains($this->mailText($n, $rejected), 'صورة الهوية غير واضحة'));
    }

    public function test_approval_is_saved_when_the_mail_service_is_down(): void
    {
        config(['mail.default' => 'brevo', 'mail.mailers.brevo.key' => 'key']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'down'], 500)]);
        Role::findOrCreate('system.admin', 'web');
        $admin = User::factory()->create(['user_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('system.admin');
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'inactive']);
        $profile = ProviderProfile::create(['user_id' => $provider->id, 'business_name' => 'C', 'status' => 'pending', 'registration_status' => 'pending', 'submitted_at' => now()]);

        $this->actingAs($admin)->post(route('admin.provider.approve', $profile))->assertRedirect(route('admin.providers.pending'));
        $this->assertSame('approved', $profile->fresh()->status);
    }
}
