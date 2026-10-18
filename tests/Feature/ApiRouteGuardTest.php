<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_provider_routes(): void
    {
        $customer = User::factory()->create(['user_type' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/provider/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_provider_cannot_access_customer_booking_routes(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider']);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/bookings')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_inactive_account_cannot_access_profile(): void
    {
        $user = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'inactive',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertForbidden();
    }

    public function test_unverified_user_cannot_access_profile(): void
    {
        $user = User::factory()->unverified()->create(['user_type' => 'customer']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertForbidden();
    }
}
