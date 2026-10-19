<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ProviderProfile;
use App\Models\SubscriptionPayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_plans_include_their_features(): void
    {
        $response = $this->actingAs($this->approvedProvider(), 'sanctum')
            ->getJson('/api/provider/subscription-plans');

        $response->assertJsonPath('data.1.slug', 'monthly')
            ->assertJsonCount(6, 'data.1.features')
            ->assertJsonPath('data.1.features.0.included', true)
            ->assertJsonPath('data.1.features.5.included', false)
            ->assertJsonPath('data.2.features.5.included', true);
    }

    public function test_free_trial_plan_starts_one_free_month(): void
    {
        $provider = $this->approvedProvider();

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'free-trial']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.plan.slug', 'free-trial');

        $subscription = $provider->providerSubscriptions()->first();
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->current_period_end->equalTo($subscription->trial_ends_at));

        // Choosing the free month again while it runs just confirms it.
        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'free-trial'])
            ->assertOk()
            ->assertJsonPath('data.status', 'trialing');
        $this->assertDatabaseCount('provider_subscriptions', 1);
    }

    public function test_free_month_is_not_granted_again_after_it_ends(): void
    {
        $provider = $this->approvedProvider();
        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'free-trial'])
            ->assertCreated();
        $provider->providerSubscriptions()->update(['current_period_end' => now()->subDay()]);

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'free-trial'])
            ->assertStatus(409)
            ->assertJsonPath('status', false);

        $this->assertDatabaseCount('provider_subscriptions', 1);
    }

    public function test_paid_plan_needs_a_transfer_proof(): void
    {
        $this->actingAs($this->approvedProvider(), 'sanctum')
            ->postJson('/api/provider/subscription', ['plan' => 'monthly'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof');

        $this->assertDatabaseCount('provider_subscriptions', 0);
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_paid_plan_proof_waits_for_admin_review(): void
    {
        Storage::fake('local');
        $provider = $this->approvedProvider();

        $response = $this->actingAs($provider, 'sanctum')->post('/api/provider/subscription', [
            'plan' => 'monthly',
            'proof' => UploadedFile::fake()->image('transfer.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.amount', '100.00')
            ->assertJsonMissingPath('data.proof_path');
        $payment = SubscriptionPayment::firstOrFail();
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertDatabaseCount('provider_subscriptions', 0);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/subscription')
            ->assertJsonPath('pending_payment.id', $payment->id);

        $this->actingAs($provider, 'sanctum')->post('/api/provider/subscription', [
            'plan' => 'yearly',
            'proof' => UploadedFile::fake()->image('again.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(409);
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
