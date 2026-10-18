<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderExternalBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_create_and_list_external_booking(): void
    {
        $provider = User::factory()->create(['user_type' => 'provider']);

        $response = $this->actingAs($provider, 'sanctum')->postJson('/api/provider/external-bookings', [
            'customer_name' => 'سارة أحمد',
            'customer_phone' => '0501234567',
            'service_name' => 'قاعة الموهبة',
            'booking_date' => '2026-10-20',
            'booking_time' => '18:00',
            'total_price' => 960.00,
            'deposit_amount' => 300.00,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'notes' => 'حجز تم خارج التطبيق',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.customer_name', 'سارة أحمد')
            ->assertJsonPath('data.service_name', 'قاعة الموهبة');

        $list = $this->actingAs($provider, 'sanctum')->getJson('/api/provider/external-bookings');

        $list->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_name', 'سارة أحمد');
    }
}
