<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_normalizes_email_and_auto_verifies_provider(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => '  User@Example.COM  ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'provider',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'provider')
            ->assertJsonPath('data.token_type', 'Bearer');
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
        ]);
        $this->assertNotNull(User::where('email', 'user@example.com')->value('email_verified_at'));
    }

    public function test_user_can_verify_email_via_hash_without_signed_expiration_check(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'user@example.com',
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $response = $this->getJson(parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'تم تفعيل البريد الإلكتروني بنجاح. يمكنك الآن تسجيل الدخول.',
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
