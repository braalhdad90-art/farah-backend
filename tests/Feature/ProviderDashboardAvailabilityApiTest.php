<?php

namespace Tests\Feature;

use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderDashboardAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_view_dashboard_and_block_a_service_date(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'متجر لافندر',
            'status' => 'approved',
            'registration_status' => 'approved',
            'payment_instructions' => 'بنك فلسطين، اسم الحساب: متجر لافندر، رقم الحساب: 1654987',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'باقة العروس',
            'service_type' => 'package',
            'price' => 800,
            'currency' => 'ILS',
            'status' => 'active',
            'is_available' => true,
            'booking_slots' => [[
                'label' => 'الفترة المسائية',
                'start_time' => '17:00',
                'end_time' => '22:00',
            ]],
        ]);
        $blockedAt = now()->addDays(3);
        $date = $blockedAt->toDateString();

        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')
            ->assertOk()
            ->assertJsonPath('data.provider.business_name', 'متجر لافندر')
            ->assertJsonPath('data.counts.packages', 1)
            ->assertJsonPath('data.provider.payment_instructions_configured', true);

        $this->actingAs($provider, 'sanctum')->putJson("/api/provider/services/{$service->id}/availability", [
            'block_dates' => [['date' => $date, 'reason' => 'التزام سابق']],
        ])->assertOk();

        $this->getJson('/api/services/'.$service->id.'/booking-options?month='.$blockedAt->format('Y-m'))
            ->assertOk()
            ->assertJsonFragment(['date' => $date, 'is_blocked_by_provider' => true, 'blocked_reason' => 'التزام سابق']);

        $this->actingAs($provider, 'sanctum')->patchJson("/api/provider/services/{$service->id}/visibility", ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.is_published', false);
    }
}
