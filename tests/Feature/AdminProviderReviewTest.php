<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProviderReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_activates_trial_once_and_records_audit_and_notification(): void
    {
        $admin = $this->adminWithRole('system.admin');
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'inactive']);
        $profile = $this->providerProfile($provider, [
            'status' => 'pending',
            'registration_status' => 'pending',
            'submitted_at' => now(),
        ]);
        $plan = SubscriptionPlan::where('slug', 'free-trial')->first()
            ?? SubscriptionPlan::create([
                'slug' => 'free-trial',
                'name' => 'Free trial',
                'price' => 0,
                'currency' => 'USD',
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ]);

        $this->actingAs($admin)->post(route('admin.provider.approve', $profile))->assertRedirect(route('admin.providers.pending'));
        $this->assertDatabaseHas('provider_profiles', ['id' => $profile->id, 'status' => 'approved']);
        $this->assertDatabaseHas('users', ['id' => $provider->id, 'status' => 'active']);
        $this->assertDatabaseHas('provider_subscriptions', [
            'provider_id' => $provider->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'trialing',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'provider_approved',
            'resource_id' => $profile->id,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $provider->id,
            'type' => 'provider_approved',
        ]);

        $this->actingAs($admin)->post(route('admin.provider.approve', $profile))->assertStatus(409);
        $this->assertDatabaseCount('provider_subscriptions', 1);
    }

    public function test_rejection_reason_is_notified_and_recorded(): void
    {
        $admin = $this->adminWithRole('ops.moderator');
        $provider = User::factory()->create(['user_type' => 'provider']);
        $profile = $this->providerProfile($provider, [
            'status' => 'pending',
            'registration_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.provider.reject', $profile), [
            'reason' => 'Please upload a readable registration document.',
        ])->assertRedirect(route('admin.providers.pending'));

        $this->assertDatabaseHas('provider_profiles', [
            'id' => $profile->id,
            'status' => 'rejected',
            'decision_reason' => 'Please upload a readable registration document.',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $provider->id,
            'body' => 'Please upload a readable registration document.',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'provider_rejected']);
    }

    public function test_finance_reviewer_cannot_approve_providers(): void
    {
        $admin = $this->adminWithRole('finance.reviewer');
        $provider = User::factory()->create(['user_type' => 'provider']);
        $profile = $this->providerProfile($provider, ['status' => 'pending', 'registration_status' => 'pending']);

        $this->actingAs($admin)->post(route('admin.provider.approve', $profile))->assertForbidden();
    }

    public function test_database_rejects_direct_audit_log_update_and_delete(): void
    {
        $log = AdminAuditLog::create([
            'action' => 'test_event',
            'resource_type' => 'test',
            'before' => ['status' => 'pending'],
            'after' => ['status' => 'approved'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::table('admin_audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
            $this->fail('The database allowed an audit log update.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id, 'action' => 'test_event']);
        }
        try {
            \Illuminate\Support\Facades\DB::table('admin_audit_logs')->where('id', $log->id)->delete();
            $this->fail('The database allowed an audit log delete.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseHas('admin_audit_logs', ['id' => $log->id, 'action' => 'test_event']);
        }
    }

    public function test_public_provider_documents_can_be_migrated_to_private_storage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $path = 'provider-documents/provider-id.pdf';
        Storage::disk('public')->put($path, 'private-document');
        $provider = User::factory()->create(['user_type' => 'provider']);
        $this->providerProfile($provider, ['identity_document_path' => $path]);

        $this->assertSame(0, Artisan::call('providers:secure-documents'));
        Storage::disk('public')->assertExists($path);

        $this->assertSame(0, Artisan::call('providers:secure-documents', ['--execute' => true]));
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_provider_document_requires_authorized_admin_and_short_lived_signature(): void
    {
        Storage::fake('local');
        $admin = $this->adminWithRole('system.admin');
        $provider = User::factory()->create(['user_type' => 'provider']);
        $path = 'provider-documents/identity.pdf';
        Storage::disk('local')->put($path, 'identity document content');
        $profile = $this->providerProfile($provider, ['identity_document_path' => $path]);
        $url = URL::temporarySignedRoute('admin.provider.document', now()->addMinutes(5), [
            'provider' => $profile->id,
            'document' => 'identity_document',
        ]);

        $response = $this->actingAs($admin)->get($url)->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $finance = $this->adminWithRole('finance.reviewer');
        $this->actingAs($finance)->get($url)->assertForbidden();
    }

    public function test_overdue_provider_reviews_are_escalated_once(): void
    {
        $admin = $this->adminWithRole('ops.moderator');
        $provider = User::factory()->create(['user_type' => 'provider']);
        $profile = $this->providerProfile($provider, [
            'status' => 'pending',
            'registration_status' => 'pending',
            'submitted_at' => now()->subHours(25),
        ]);

        $this->assertSame(0, Artisan::call('providers:escalate-overdue'));
        $this->assertNotNull($profile->fresh()->escalated_at);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'type' => 'provider_review_overdue',
        ]);
        $this->assertSame(0, Artisan::call('providers:escalate-overdue'));
        $this->assertDatabaseCount('app_notifications', 1);
    }

    public function test_booking_payment_proofs_are_read_only_for_admins(): void
    {
        $admin = $this->adminWithRole('finance.reviewer');
        $customer = User::factory()->create(['user_type' => 'customer']);
        $provider = User::factory()->create(['user_type' => 'provider']);
        $service = Service::factory()->create(['provider_id' => $provider->id]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'workflow_status' => 'review_under_proof',
        ]);
        $payment = BookingPayment::create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'provider_id' => $provider->id,
            'reference' => 'PAY-ADMIN-TEST',
            'amount' => 50,
            'method' => 'bank_transfer',
            'proof_path' => 'payment-proofs/test-proof.pdf',
            'status' => 'submitted',
        ]);

        // Only the provider approves booking payments; the admin page lists them.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.finance.payment.review'));
        $this->actingAs($admin)->get(route('admin.finance.payments'))
            ->assertOk()
            ->assertSee('PAY-ADMIN-TEST')
            ->assertDontSee('name="decision"', false);
        $this->actingAs($admin)->post('/admin/finance/payments/'.$payment->id.'/review', ['decision' => 'confirmed'])
            ->assertNotFound();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'workflow_status' => 'review_under_proof']);
    }

    private function adminWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $admin = User::factory()->create(['user_type' => 'admin', 'status' => 'active']);
        $admin->assignRole($role);

        return $admin;
    }

    private function providerProfile(User $provider, array $attributes = []): ProviderProfile
    {
        return ProviderProfile::create(array_merge([
            'user_id' => $provider->id,
            'business_name' => 'Test provider business',
            'status' => 'pending',
            'registration_status' => 'pending',
        ], $attributes));
    }
}
