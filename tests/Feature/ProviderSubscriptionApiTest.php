<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ProviderProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_view_subscription_plans(): void
    {
        $provider = $this->approvedProvider();

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/subscription-plans');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.price', '0.00')
            ->assertJsonPath('data.1.price', '100.00')
            ->assertJsonPath('data.2.price', '175.00');
    }

    public function test_first_provider_subscription_has_one_free_month(): void
    {
        $provider = $this->approvedProvider();

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'monthly']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.plan.slug', 'free-trial');

        $subscription = $provider->providerSubscriptions()->first();
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->current_period_end->equalTo($subscription->trial_ends_at));
        $this->assertTrue($subscription->current_period_start->lessThan($subscription->trial_ends_at));
    }

    public function test_free_month_is_not_granted_again_when_changing_plan(): void
    {
        $provider = $this->approvedProvider();

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'monthly'])
            ->assertCreated();

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'yearly']);

        $response->assertStatus(409)
            ->assertJsonPath('status', false);

        $this->assertDatabaseCount('provider_subscriptions', 1);
    }

    public function test_regular_user_cannot_access_provider_subscription_api(): void
    {
        $user = User::factory()->create(['user_type' => 'customer']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/provider/subscription-plans')
            ->assertForbidden();
    }

    public function test_pending_provider_cannot_start_a_subscription(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Pending provider',
            'status' => 'pending',
            'registration_status' => 'pending',
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'monthly'])
            ->assertForbidden();
    }

    private function approvedProvider(): User
    {
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Approved provider',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);

        return $provider;
    }
}
