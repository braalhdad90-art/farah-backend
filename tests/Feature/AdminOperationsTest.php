<?php

namespace Tests\Feature;

use App\Models\AdminRefund;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\ProviderRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ops.moderator', 'finance.reviewer', 'system.admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_ops_admin_can_moderate_reviews_and_public_rating_excludes_hidden_reviews(): void
    {
        $admin = $this->admin('ops.moderator');
        $service = Service::factory()->create(['rating_avg' => 4, 'reviews_count' => 1]);
        $review = Review::factory()->create([
            'service_id' => $service->id,
            'rating' => 5,
            'comment' => 'Review for moderation',
        ]);

        $this->actingAs($admin)->post(route('admin.reviews.action', $review), [
            'action' => 'hide',
            'reason' => 'Contains prohibited personal information.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'is_visible' => false]);
        $this->assertSame(0, (int) $service->fresh()->reviews_count);
        $this->assertSame(0.0, (float) $service->fresh()->rating_avg);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'review_hidden', 'resource_id' => $review->id]);

        $this->actingAs($admin)->post(route('admin.reviews.action', $review), ['action' => 'restore'])
            ->assertRedirect();
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'is_visible' => true]);
        $this->assertSame(1, (int) $service->fresh()->reviews_count);
        $this->assertSame(5.0, (float) $service->fresh()->rating_avg);
    }

    public function test_system_admin_can_manage_categories_plans_team_and_public_settings(): void
    {
        $admin = $this->admin('system.admin');
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.categories.create'), [
            'name' => 'Wedding Halls',
            'slug' => 'wedding-halls-admin',
            'status' => 'active',
        ])->assertRedirect();
        $this->assertDatabaseHas('categories', ['slug' => 'wedding-halls-admin']);

        $plan = SubscriptionPlan::where('slug', 'monthly')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Monthly revised',
            'description' => 'Updated monthly plan',
            'price' => 125,
            'currency' => 'ILS',
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id, 'price' => 125]);

        $teammate = $this->admin('finance.reviewer');
        $this->actingAs($admin)->post(route('admin.team.update_role', $teammate), [
            'role' => 'ops.moderator',
            'status' => 'active',
        ])->assertRedirect();
        $this->assertTrue($teammate->fresh()->hasRole('ops.moderator'));

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'platform_name' => 'Farah',
            'support_email' => 'help@example.test',
            'support_phone' => '+9700000000',
        ])->assertRedirect();
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.platform_name', 'Farah')
            ->assertJsonPath('data.support_email', 'help@example.test');

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'system_settings_updated']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'subscription_plan_updated']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'admin_account_updated']);
        $this->assertNotNull($category->fresh());
    }

    public function test_inactive_admin_loses_access_to_dashboard_routes(): void
    {
        $admin = $this->admin('ops.moderator');
        $admin->update(['status' => 'inactive']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_finance_reviewer_cannot_access_content_moderation(): void
    {
        $admin = $this->admin('finance.reviewer');

        $this->actingAs($admin)->get(route('admin.reviews'))->assertForbidden();
    }

    public function test_system_admin_can_render_admin_pages_in_arabic_and_validation_errors_are_localized(): void
    {
        $admin = $this->admin('system.admin');
        $pages = [
            'admin.dashboard',
            'admin.providers.pending',
            'admin.providers',
            'admin.reviews',
            'admin.content.services',
            'admin.finance.payments',
            'admin.finance.refunds',
            'admin.audit-log',
            'admin.team',
            'admin.categories',
            'admin.plans',
            'admin.settings',
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($admin)->get(route($page));
            $response
                ->assertOk()
                ->assertSee('lang="ar" dir="rtl"', false)
                ->assertSee('/css/admin.css', false);

            if ($page === 'admin.audit-log') {
                $response->assertSee('/css/admin-audit-log.css', false);
            }
        }

        auth()->logout();
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('/css/admin-login.css', false);

        $this->actingAs($admin)->from(route('admin.categories'))->post(route('admin.categories.create'), [])
            ->assertSessionHasErrors('name');

        $this->get(route('admin.categories'))
            ->assertOk()
            ->assertSee('حقل الاسم مطلوب.');
    }

    public function test_finance_admin_can_record_and_track_refund_without_exceeding_confirmed_payments(): void
    {
        $admin = $this->admin('finance.reviewer');
        $customer = User::factory()->create(['user_type' => 'customer']);
        $service = Service::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'service_id' => $service->id,
            'provider_id' => $service->provider_id,
            'workflow_status' => 'confirmed',
            'status' => 'confirmed',
            'reference' => 'REFUND-TEST-1',
        ]);
        BookingPayment::create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'provider_id' => $service->provider_id,
            'reference' => 'PAY-REFUND-1',
            'amount' => 100,
            'method' => 'bank_transfer',
            'proof_path' => 'proofs/test.jpg',
            'status' => 'confirmed',
        ]);

        $this->actingAs($admin)->post(route('admin.finance.refunds.create', $booking), [
            'amount' => 80,
            'reason' => 'Service was cancelled by provider.',
        ])->assertRedirect();

        $refund = AdminRefund::firstOrFail();
        $this->actingAs($admin)->post(route('admin.finance.refunds.create', $booking), [
            'amount' => 30,
            'reason' => 'Another refund request.',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($admin)->post(route('admin.finance.refunds.decision', $refund), [
            'action' => 'approve',
        ])->assertForbidden();

        $approver = $this->admin('system.admin');
        $this->actingAs($approver)->post(route('admin.finance.refunds.decision', $refund), [
            'action' => 'approve',
        ])->assertRedirect();
        $this->actingAs($approver)->post(route('admin.finance.refunds.decision', $refund), [
            'action' => 'mark_processed',
            'external_reference' => 'BANK-TRANSFER-REF-204',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.finance.refunds.decision', $refund), [
            'action' => 'mark_processed',
            'external_reference' => 'BANK-TRANSFER-REF-204',
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_refunds', ['id' => $refund->id, 'status' => 'processed', 'amount' => 80, 'external_reference' => 'BANK-TRANSFER-REF-204']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'refund_requested', 'resource_id' => $refund->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'refund_mark_processed', 'resource_id' => $refund->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $customer->id, 'type' => 'refund_processed']);
    }

    public function test_provider_rejection_also_sends_mail_notification(): void
    {
        Notification::fake();
        $admin = $this->admin('ops.moderator');
        $provider = User::factory()->create(['user_type' => 'provider']);
        $profile = \App\Models\ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'Mail notification provider',
            'status' => 'pending',
            'registration_status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.provider.reject', $profile), [
            'reason' => 'Please submit a clearer identity document.',
        ])->assertRedirect(route('admin.providers.pending'));

        Notification::assertSentTo($provider, ProviderRejectedNotification::class);
    }

    public function test_inactive_category_hides_its_service_from_public_api(): void
    {
        $service = Service::factory()->create(['status' => 'active', 'is_available' => true]);
        $service->category()->update(['status' => 'inactive']);
        ProviderProfile::create([
            'user_id' => $service->provider_id,
            'business_name' => 'Approved public provider',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);

        $this->getJson('/api/services/'.$service->id)->assertNotFound();
    }

    public function test_ops_admin_can_hide_and_restore_service_without_provider_override(): void
    {
        $admin = $this->admin('ops.moderator');
        $service = Service::factory()->create(['status' => 'active', 'is_available' => true]);
        ProviderProfile::create([
            'user_id' => $service->provider_id,
            'business_name' => 'Approved service provider',
            'status' => 'approved',
            'registration_status' => 'approved',
        ]);

        $this->actingAs($admin)->post(route('admin.content.services.visibility', $service), [
            'action' => 'hide',
            'reason' => 'Listing contains prohibited content.',
        ])->assertRedirect();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'admin_hidden' => true]);
        $this->getJson('/api/services/'.$service->id)->assertNotFound();

        $service->update(['status' => 'active', 'is_available' => true]);
        $this->getJson('/api/services/'.$service->id)->assertNotFound();

        $this->actingAs($admin)->post(route('admin.content.services.visibility', $service), [
            'action' => 'restore',
        ])->assertRedirect();
        $this->getJson('/api/services/'.$service->id)->assertOk();
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'service_hidden', 'resource_id' => $service->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'service_restored', 'resource_id' => $service->id]);
    }

    public function test_ops_admin_can_download_csv_and_formula_values_are_neutralized(): void
    {
        $admin = $this->admin('ops.moderator');
        $customer = User::factory()->create(['user_type' => 'customer', 'name' => '=HYPERLINK("bad")']);
        $service = Service::factory()->create();
        Booking::factory()->create([
            'user_id' => $customer->id,
            'service_id' => $service->id,
            'provider_id' => $service->provider_id,
            'workflow_status' => 'confirmed',
            'reference' => 'CSV-TEST-1',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.export.bookings'));

        $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        $this->assertStringContainsString('CSV-TEST-1', $response->streamedContent());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'report_exported', 'resource_type' => 'csv_report']);
    }

    private function admin(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['user_type' => 'admin']);
        $user->assignRole($role);

        return $user;
    }
}
