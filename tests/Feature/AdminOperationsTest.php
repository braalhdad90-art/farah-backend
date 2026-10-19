<?php

namespace Tests\Feature;

use App\Models\AdminRefund;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPayment;
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

        // فرح: إدارة الأسئلة الشائعة لمركز المساعدة.
        $this->actingAs($admin)->post(route('admin.faqs.create'), [
            'audience' => 'provider',
            'question' => 'How do I get paid?',
            'answer' => 'Add your payout account.',
            'sort_order' => 3,
            'is_active' => 1,
        ])->assertRedirect();
        $faq = \App\Models\Faq::where('question', 'How do I get paid?')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.faqs.update', $faq), [
            'audience' => 'all',
            'question' => 'How do I get paid?',
            'answer' => 'Updated answer.',
        ])->assertRedirect();
        $this->assertDatabaseHas('faqs', ['id' => $faq->id, 'audience' => 'all', 'is_active' => false]);
        $this->actingAs($admin)->delete(route('admin.faqs.delete', $faq))->assertRedirect();
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);

        $plan = SubscriptionPlan::where('slug', 'monthly')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Monthly revised',
            'description' => 'Updated monthly plan',
            'price' => 125,
            'currency' => 'ILS',
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => 1,
            'included_features' => "First\n\nSecond",
            'excluded_features' => 'Third',
        ])->assertRedirect();
        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id, 'price' => 125]);
        $this->assertSame([
            ['name' => 'First', 'included' => true],
            ['name' => 'Second', 'included' => true],
            ['name' => 'Third', 'included' => false],
        ], $plan->fresh()->features);

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
            'bank_name' => 'Admin bank',
            'bank_account_number' => '42',
            'bank_iban' => 'PS00TEST',
            'provider_review_sla_hours' => 12,
            'payment_due_hours' => 6,
            'working_hours' => 'Sat-Thu 9-5',
        ])->assertRedirect();
        $this->assertSame(12, \App\Models\AppSetting::hours('provider_review_sla_hours'));
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.bank_transfer.bank_name', 'Admin bank')
            ->assertJsonPath('data.bank_transfer.iban', 'PS00TEST')
            ->assertJsonPath('data.working_hours', 'Sat-Thu 9-5')
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
            'admin.finance.subscriptions',
            'admin.finance.payouts',
            'admin.audit-log',
            'admin.team',
            'admin.categories',
            'admin.plans',
            'admin.faqs',
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

    public function test_finance_admin_activates_paid_plan_after_reviewing_transfer(): void
    {
        $admin = $this->admin('finance.reviewer');
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        $trialEnd = now()->addDays(10);
        $trial = ProviderSubscription::create([
            'provider_id' => $provider->id,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'free-trial')->value('id'),
            'status' => 'trialing',
            'trial_ends_at' => $trialEnd,
            'current_period_start' => now()->subDays(20),
            'current_period_end' => $trialEnd,
        ]);
        $monthly = SubscriptionPlan::where('slug', 'monthly')->firstOrFail();
        $payment = SubscriptionPayment::create([
            'provider_id' => $provider->id,
            'subscription_plan_id' => $monthly->id,
            'amount' => 100,
            'currency' => 'ILS',
            'proof_path' => 'subscription-proofs/test.jpg',
            'status' => 'submitted',
        ]);

        $this->actingAs($admin)->get(route('admin.finance.subscriptions'))
            ->assertOk()
            ->assertSee($provider->name);

        $this->actingAs($admin)->post(route('admin.finance.subscription.review', $payment), ['decision' => 'confirmed'])
            ->assertRedirect(route('admin.finance.subscriptions'));

        $active = $provider->activeProviderSubscription()->firstOrFail();
        $this->assertSame('active', $active->status);
        $this->assertSame($monthly->id, $active->subscription_plan_id);
        // The ten trial days left carry over to the paid month.
        $this->assertTrue($active->current_period_end->isSameDay($trialEnd->copy()->addMonthNoOverflow()));
        $this->assertSame('replaced', $trial->fresh()->status);
        $this->assertDatabaseHas('subscription_payments', ['id' => $payment->id, 'status' => 'confirmed', 'provider_subscription_id' => $active->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'subscription_payment_confirmed', 'resource_id' => $payment->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $provider->id, 'type' => 'subscription_activated']);

        $this->actingAs($admin)->post(route('admin.finance.subscription.review', $payment), ['decision' => 'rejected', 'reason' => 'not_received'])
            ->assertStatus(409);
    }

    public function test_rejected_transfer_needs_a_note_and_activates_nothing(): void
    {
        $admin = $this->admin('finance.reviewer');
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        $payment = SubscriptionPayment::create([
            'provider_id' => $provider->id,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'yearly')->value('id'),
            'amount' => 175,
            'currency' => 'ILS',
            'proof_path' => 'subscription-proofs/test.jpg',
            'status' => 'submitted',
        ]);

        $this->actingAs($admin)->post(route('admin.finance.subscription.review', $payment), ['decision' => 'rejected'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.finance.subscription.review', $payment), ['decision' => 'rejected', 'reason' => 'other'])
            ->assertSessionHasErrors('review_note');

        $this->actingAs($admin)->post(route('admin.finance.subscription.review', $payment), [
            'decision' => 'rejected',
            'reason' => 'amount_mismatch',
        ])->assertRedirect();

        $this->assertDatabaseHas('subscription_payments', [
            'id' => $payment->id,
            'status' => 'rejected',
            'review_note' => 'المبلغ المحوّل لا يطابق سعر الباقة',
        ]);
        $this->assertDatabaseCount('provider_subscriptions', 0);
    }

    public function test_system_admin_creates_a_team_account_with_one_role(): void
    {
        $admin = $this->admin('system.admin');

        $this->actingAs($admin)->post(route('admin.team.create'), [
            'name' => 'Finance teammate',
            'email' => 'Finance@Example.test',
            'role' => 'finance.reviewer',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ])->assertRedirect();

        $created = User::where('email', 'finance@example.test')->firstOrFail();
        $this->assertSame('admin', $created->user_type);
        $this->assertSame(['finance.reviewer'], $created->getRoleNames()->all());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'admin_account_created', 'resource_id' => $created->id]);

        // Ops moderators cannot create accounts.
        $this->actingAs($this->admin('ops.moderator'))->post(route('admin.team.create'), [
            'name' => 'Nope',
            'email' => 'nope@example.test',
            'role' => 'system.admin',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ])->assertForbidden();
    }

    public function test_admin_create_command_only_creates_missing_accounts(): void
    {
        $this->artisan('admin:create', [
            '--if-missing' => true, '--name' => 'Boss', '--email' => 'boss@example.test', '--password' => 'first-pass-1',
        ])->assertSuccessful();
        $boss = User::where('email', 'boss@example.test')->firstOrFail();
        $this->assertTrue($boss->hasRole('system.admin'));
        $boss->syncRoles(['finance.reviewer']);

        $this->artisan('admin:create', [
            '--if-missing' => true, '--name' => 'Boss', '--email' => 'boss@example.test', '--password' => 'other-pass-2',
        ])->assertSuccessful();
        $this->assertTrue($boss->fresh()->hasRole('finance.reviewer'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('first-pass-1', $boss->fresh()->password));
    }

    public function test_subscription_proof_is_held_by_the_reviewer_who_opened_it(): void
    {
        $first = $this->admin('finance.reviewer');
        $second = $this->admin('finance.reviewer');
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        $payment = SubscriptionPayment::create([
            'provider_id' => $provider->id,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'monthly')->value('id'),
            'amount' => 100,
            'currency' => 'ILS',
            'proof_path' => 'subscription-proofs/test.jpg',
            'status' => 'submitted',
        ]);

        $this->actingAs($first)->get(route('admin.finance.subscription.show', $payment))
            ->assertOk()
            ->assertSee('name="decision"', false);
        $this->assertSame($first->id, $payment->fresh()->review_locked_by);

        $this->actingAs($second)->get(route('admin.finance.subscription.show', $payment))
            ->assertOk()
            ->assertSee($first->name)
            ->assertDontSee('name="decision"', false);
        $this->actingAs($second)->post(route('admin.finance.subscription.review', $payment), ['decision' => 'confirmed'])
            ->assertStatus(423);

        // The hold expires after 15 minutes.
        $this->travel(16)->minutes();
        $this->actingAs($second)->post(route('admin.finance.subscription.review', $payment), ['decision' => 'confirmed'])
            ->assertRedirect();
        $this->assertDatabaseHas('subscription_payments', ['id' => $payment->id, 'status' => 'confirmed', 'reviewed_by' => $second->id, 'review_locked_by' => null]);
    }

    public function test_finance_records_a_payout_and_ops_cannot(): void
    {
        $finance = $this->admin('finance.reviewer');
        $provider = User::factory()->create(['user_type' => 'provider', 'status' => 'active']);
        ProviderProfile::create(['user_id' => $provider->id, 'business_name' => 'Payout hall', 'status' => 'approved', 'registration_status' => 'approved']);
        $booking = Booking::factory()->create(['provider_id' => $provider->id, 'reference' => 'BKG-PAYOUT-1']);

        $payload = [
            'provider_id' => $provider->id,
            'booking_reference' => $booking->reference,
            'amount' => 320.5,
            'currency' => 'ILS',
            'method' => 'bank_transfer',
            'reference' => 'TRX-778',
            'paid_at' => now()->toDateString(),
        ];
        $this->actingAs($this->admin('ops.moderator'))->post(route('admin.finance.payouts.create'), $payload)->assertForbidden();

        $this->actingAs($finance)->post(route('admin.finance.payouts.create'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('provider_payouts', ['provider_id' => $provider->id, 'booking_id' => $booking->id, 'reference' => 'TRX-778', 'recorded_by' => $finance->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'payout_recorded']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $provider->id, 'type' => 'payout_recorded']);
        $this->actingAs($finance)->get(route('admin.finance.payouts'))->assertOk()->assertSee('TRX-778');

        $this->actingAs($finance)->post(route('admin.finance.payouts.create'), ['booking_reference' => 'NOT-HIS'] + $payload)
            ->assertSessionHasErrors('booking_reference');
    }

    public function test_completed_bookings_cannot_be_refunded(): void
    {
        $finance = $this->admin('finance.reviewer');
        $booking = Booking::factory()->create(['workflow_status' => 'completed', 'status' => 'completed']);

        $this->actingAs($finance)->post(route('admin.finance.refunds.create', $booking), [
            'amount' => 10,
            'reason' => 'Customer asked after the event.',
        ])->assertStatus(409);
        $this->assertDatabaseCount('admin_refunds', 0);
    }

    public function test_finance_exports_subscriptions_and_audit_log_is_searchable(): void
    {
        $system = $this->admin('system.admin');
        $finance = $this->admin('finance.reviewer');

        $this->actingAs($finance)->get(route('admin.finance.export.subscriptions'))->assertOk();
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'report_exported', 'actor_id' => $finance->id]);

        $this->actingAs($system)->get(route('admin.audit-log', ['action' => 'report_exported', 'actor' => $finance->email]))
            ->assertOk()
            ->assertSee($finance->name);
        $this->actingAs($system)->get(route('admin.audit-log', ['action' => 'payout_recorded']))
            ->assertOk()
            ->assertDontSee($finance->name);
    }

    public function test_identity_documents_are_purged_thirty_days_after_the_decision(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('provider-documents/old.jpg', 'x');
        \Illuminate\Support\Facades\Storage::disk('local')->put('provider-documents/new.jpg', 'x');
        $old = ProviderProfile::create(['user_id' => User::factory()->create()->id, 'business_name' => 'Old', 'status' => 'approved', 'registration_status' => 'approved', 'decided_at' => now()->subDays(31), 'identity_document_path' => 'provider-documents/old.jpg']);
        $recent = ProviderProfile::create(['user_id' => User::factory()->create()->id, 'business_name' => 'Recent', 'status' => 'approved', 'registration_status' => 'approved', 'decided_at' => now()->subDays(5), 'identity_document_path' => 'provider-documents/new.jpg']);

        $this->artisan('providers:purge-documents')->assertSuccessful();

        $this->assertNull($old->fresh()->identity_document_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing('provider-documents/old.jpg');
        $this->assertSame('provider-documents/new.jpg', $recent->fresh()->identity_document_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('provider-documents/new.jpg');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'provider_documents_purged', 'resource_id' => $old->id]);
    }

    private function admin(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['user_type' => 'admin']);
        $user->assignRole($role);

        return $user;
    }
}
