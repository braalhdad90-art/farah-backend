<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_automatically_verifies_user_and_returns_access_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'عميل جديد',
            'email' => 'new@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'customer',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email_verified_at', fn ($value) => $value !== null);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertNotNull(User::where('email', 'new@example.test')->value('email_verified_at'));
        Notification::assertNothingSent();

        $this->withToken($token)->getJson('/api/profile')->assertOk();
    }

    public function test_notification_settings_are_created_with_safe_defaults_and_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/notification-settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.offers', true);

        $this->actingAs($user, 'sanctum')->putJson('/api/notification-settings', [
            'enabled' => false,
            'offers' => false,
        ])->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.offers', false);
    }

    public function test_primary_location_is_returned_in_profile_and_can_be_changed(): void
    {
        $user = User::factory()->create();
        $first = Location::create(['user_id' => $user->id, 'label' => 'المنزل', 'address' => 'العنوان الأول', 'is_primary' => true]);
        $second = Location::create(['user_id' => $user->id, 'label' => 'العمل', 'address' => 'العنوان الثاني', 'is_primary' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.primary_location.id', $first->id);

        $this->actingAs($user, 'sanctum')->patchJson("/api/locations/{$second->id}/primary")
            ->assertOk()
            ->assertJsonPath('data.id', $second->id);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_account_deletion_requires_reauthentication(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/account', [
            'current_password' => 'wrong-password',
        ])->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
