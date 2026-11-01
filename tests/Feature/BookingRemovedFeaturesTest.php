<?php

namespace Tests\Feature;

use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// فرح: العميل بيشيل بس المزايا اللي المزود سمح بإزالتها، والمزود بيشوفها بتفاصيل الطلب.
class BookingRemovedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Service
    {
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Hall',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);

        return Service::create([
            'provider_id' => $provider->id,
            'title' => 'Hall',
            'price' => 1000,
            'currency' => 'ILS',
            'status' => 'active',
            'features' => ['Chairs', 'Flowers', 'Lights'],
            'removable_features' => ['Flowers', 'Lights'],
            'booking_slots' => [['label' => 'Evening', 'start_time' => '17:00', 'end_time' => '22:00', 'price' => 1000]],
        ]);
    }

    private function payload(array $removed): array
    {
        return [
            'booking_date' => now()->addDays(3)->toDateString(),
            'booking_time' => '17:00',
            'booking_slot' => 'Evening',
            'removed_features' => $removed,
        ];
    }

    public function test_removable_features_are_saved_and_shown_to_the_provider(): void
    {
        $service = $this->service();
        $customer = User::factory()->create(['user_type' => 'customer']);

        $id = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/services/{$service->id}/bookings", $this->payload(['Flowers', 'Flowers', ' ']))
            ->assertCreated()
            ->json('data.id') ?? \App\Models\Booking::latest('id')->value('id');

        $this->assertSame(['Flowers'], \App\Models\Booking::findOrFail($id)->removed_features);

        $this->actingAs($service->provider, 'sanctum')
            ->getJson("/api/provider/bookings/{$id}")
            ->assertOk()
            ->assertJsonPath('data.removed_features', ['Flowers']);
    }

    public function test_a_feature_the_provider_did_not_allow_is_refused(): void
    {
        $service = $this->service();
        $customer = User::factory()->create(['user_type' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/services/{$service->id}/bookings", $this->payload(['Chairs']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('removed_features');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_without_removed_features_still_works(): void
    {
        $service = $this->service();
        $customer = User::factory()->create(['user_type' => 'customer']);
        $payload = $this->payload([]);
        unset($payload['removed_features']);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/services/{$service->id}/bookings", $payload)
            ->assertCreated();
        $this->assertNull(\App\Models\Booking::first()->removed_features);
    }
}
