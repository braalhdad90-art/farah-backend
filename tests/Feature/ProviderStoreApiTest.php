<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ProviderSubscription;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderStoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_provider_dashboard_has_no_invented_stats(): void
    {
        $provider = $this->provider();

        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')
            ->assertOk()
            ->assertJsonPath('data.provider.category.name', 'Flowers')
            ->assertJsonPath('data.provider.is_bookable', false)
            ->assertJsonPath('data.store_stats.completed_bookings', 0)
            ->assertJsonPath('data.store_stats.avg_response_minutes', null)
            ->assertJsonPath('data.store_stats.commitment_rate', null)
            ->assertJsonPath('data.counts.services', 0)
            ->assertJsonPath('data.unread_notifications', 0);
    }

    public function test_store_stats_come_from_provider_responses(): void
    {
        $provider = $this->provider();
        $this->activeSubscription($provider);
        $service = Service::factory()->create(['provider_id' => $provider->id, 'status' => 'active', 'is_available' => true]);
        $base = now()->subDays(2);
        // Answered after 30 and 90 minutes.
        foreach ([30, 90] as $minutes) {
            $booking = Booking::factory()->create(['provider_id' => $provider->id, 'service_id' => $service->id, 'workflow_status' => 'completed', 'status' => 'completed']);
            $booking->forceFill(['created_at' => $base, 'provider_responded_at' => $base->copy()->addMinutes($minutes)])->save();
        }
        // One request expired without an answer.
        Booking::factory()->create(['provider_id' => $provider->id, 'service_id' => $service->id, 'workflow_status' => 'expired', 'payment_due_at' => null]);
        Booking::factory()->create(['provider_id' => $provider->id, 'service_id' => $service->id, 'workflow_status' => 'provider_pending']);

        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')
            ->assertOk()
            ->assertJsonPath('data.provider.is_bookable', true)
            ->assertJsonPath('data.store_stats.completed_bookings', 2)
            ->assertJsonPath('data.store_stats.avg_response_minutes', 60)
            ->assertJsonPath('data.store_stats.commitment_rate', 67)
            ->assertJsonPath('data.counts.new_orders', 1)
            ->assertJsonPath('data.counts.new_orders_today', 1);
    }

    public function test_provider_decision_records_the_response_time(): void
    {
        $provider = $this->provider();
        $booking = Booking::factory()->create(['provider_id' => $provider->id, 'workflow_status' => 'provider_pending', 'status' => 'pending']);

        $this->actingAs($provider, 'sanctum')->postJson("/api/provider/bookings/{$booking->id}/decision", [
            'decision' => 'rejected',
            'deposit_amount' => 0,
            'provider_note' => 'Fully booked that day.',
        ])->assertOk();

        $this->assertNotNull($booking->fresh()->provider_responded_at);
    }

    public function test_service_with_offer_price_draft_and_removable_features(): void
    {
        $provider = $this->provider();
        $this->activeSubscription($provider);

        $response = $this->actingAs($provider, 'sanctum')->postJson('/api/provider/services', [
            'title' => 'Bridal bouquet',
            'category_id' => $provider->providerProfile->category_id,
            'description' => 'White tulips',
            'price' => 800,
            'offer_price' => 600,
            'execution_duration' => '24h',
            'features' => ['Ribbon', 'Gift box'],
            'removable_features' => ['Gift box'],
            'save_as_draft' => true,
        ])->assertCreated();

        $id = $response->json('data.id');
        $response->assertJsonPath('data.is_draft', true)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.pricing.price', 600)
            ->assertJsonPath('data.pricing.original_price', 800)
            ->assertJsonPath('data.features', ['Ribbon', 'Gift box'])
            ->assertJsonPath('data.feature_items.0.removable', false)
            ->assertJsonPath('data.feature_items.1.removable', true);

        // Drafts stay out of the public catalogue until activated.
        $this->getJson("/api/services/{$id}")->assertNotFound();
        $this->actingAs($provider, 'sanctum')->patchJson("/api/provider/services/{$id}/visibility", ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.is_draft', false);

        $this->actingAs($provider, 'sanctum')->postJson('/api/provider/services', [
            'title' => 'Bad offer',
            'price' => 100,
            'offer_price' => 150,
            'features' => ['A'],
            'removable_features' => ['Not listed'],
        ])->assertStatus(422)->assertJsonValidationErrors(['offer_price', 'removable_features.0']);
    }

    private function provider(): User
    {
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Lavender',
            'category_id' => Category::factory()->create(['name' => 'Flowers'])->id,
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);

        return $provider;
    }

    private function activeSubscription(User $provider): void
    {
        ProviderSubscription::create([
            'provider_id' => $provider->id,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'free-trial')->value('id'),
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(20),
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);
    }
}
