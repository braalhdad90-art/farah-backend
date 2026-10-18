<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingPaymentWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_create_a_booking_for_a_time_that_has_passed_today(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $customer = User::factory()->create(['user_type' => 'customer']);
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'صالة أورنا',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'صالة أورنا',
            'price' => 1400,
            'currency' => 'ILS',
            'status' => 'active',
            'booking_slots' => [[
                'label' => 'الفترة المسائية',
                'start_time' => '14:00',
                'end_time' => '18:00',
                'price' => 1400,
            ]],
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/services/{$service->id}/bookings", [
                'booking_date' => now()->toDateString(),
                'booking_time' => '14:00',
                'booking_slot' => 'الفترة المسائية',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_time');
    }

    public function test_customer_cannot_book_the_same_service_slot_twice(): void
    {
        $customer = User::factory()->create(['user_type' => 'customer']);
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Orna Hall',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'Orna Hall',
            'price' => 1400,
            'currency' => 'ILS',
            'status' => 'active',
            'booking_slots' => [[
                'label' => 'Evening',
                'start_time' => '17:00',
                'end_time' => '22:00',
                'price' => 1400,
            ]],
        ]);
        $payload = [
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '17:00',
            'booking_slot' => 'Evening',
        ];

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/services/{$service->id}/bookings", $payload)
            ->assertCreated();
        $this->postJson("/api/services/{$service->id}/bookings", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_slot');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_customer_booking_is_approved_paid_and_confirmed_by_provider(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['user_type' => 'customer']);
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'صالة أورنا',
            'status' => 'approved',
            'registration_status' => 'approved',
            'payment_instructions' => 'بنك فلسطين - اسم الحساب: صالة أورنا - رقم الحساب: 123456',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'صالة أورنا',
            'price' => 1400,
            'currency' => 'ILS',
            'status' => 'active',
            'booking_slots' => [[
                'label' => 'الفترة المسائية',
                'start_time' => '17:00',
                'end_time' => '22:00',
                'price' => 1500,
            ]],
        ]);

        $create = $this->actingAs($customer, 'sanctum')->postJson("/api/services/{$service->id}/bookings", [
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '17:00',
            'booking_slot' => 'الفترة المسائية',
        ]);
        $create->assertCreated()->assertJsonPath('data.status', 'provider_pending');
        $booking = Booking::firstOrFail();
        $this->assertSame(1500.0, (float) $booking->selected_price);

        $this->actingAs($provider, 'sanctum')->postJson("/api/provider/bookings/{$booking->id}/decision", [
            'decision' => 'accepted',
            'deposit_amount' => 500,
        ])->assertOk()->assertJsonPath('data.status', 'payment_awaiting');

        $this->withHeader('Accept', 'application/json')->actingAs($customer, 'sanctum')->post("/api/bookings/{$booking->id}/payment-proofs", [
            'amount' => 499,
            'method' => 'bank_transfer',
            'proof' => UploadedFile::fake()->create('incorrect-transfer.png', 100, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('booking_payments', 0);

        $this->actingAs($customer, 'sanctum')->post("/api/bookings/{$booking->id}/payment-proofs", [
            'amount' => 500,
            'method' => 'bank_transfer',
            'proof' => UploadedFile::fake()->create('transfer.png', 100, 'image/png'),
        ])->assertCreated()->assertJsonPath('data.status', 'review_under_proof');

        $paymentId = $booking->fresh()->payments()->firstOrFail()->id;
        $this->actingAs($provider, 'sanctum')->postJson("/api/provider/bookings/{$booking->id}/payments/{$paymentId}/review", [
            'decision' => 'rejected',
            'review_note' => 'Please upload a clearer receipt.',
        ])->assertOk()->assertJsonPath('data.status', 'payment_awaiting');

        $this->actingAs($customer, 'sanctum')->post("/api/bookings/{$booking->id}/payment-proofs", [
            'amount' => 500,
            'method' => 'bank_transfer',
            'proof' => UploadedFile::fake()->create('replacement-transfer.png', 100, 'image/png'),
        ])->assertCreated()->assertJsonPath('data.status', 'review_under_proof');

        $paymentId = $booking->fresh()->payments()->latest('id')->firstOrFail()->id;
        $this->actingAs($provider, 'sanctum')->postJson("/api/provider/bookings/{$booking->id}/payments/{$paymentId}/review", [
            'decision' => 'confirmed',
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'workflow_status' => 'confirmed', 'status' => 'confirmed']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $customer->id, 'type' => 'payment_confirmed']);
    }

    public function test_scheduled_command_expires_bookings_past_their_deadline(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $customer = User::factory()->create(['user_type' => 'customer']);
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'صالة أورنا',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'صالة أورنا',
            'price' => 1400,
            'currency' => 'ILS',
            'status' => 'active',
        ]);
        $booking = Booking::create([
            'reference' => 'BKG-EXPIRING',
            'user_id' => $customer->id,
            'service_id' => $service->id,
            'provider_id' => $provider->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '17:00',
            'total_price' => 1400,
            'selected_price' => 1400,
            'deposit_amount' => 280,
            'remaining_amount' => 1120,
            'status' => 'pending',
            'workflow_status' => 'provider_pending',
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('bookings:expire-stale');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'workflow_status' => 'expired',
        ]);
    }
}
