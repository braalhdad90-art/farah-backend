<?php

namespace Tests\Feature;

use App\Models\ProviderSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// فرح: تمديد الفترات المجانية الحالية لمدة الباقة (3 أشهر) بدون ما نلمس الباقات المدفوعة.
class ExtendCurrentTrialsTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        (require database_path('migrations/2026_11_02_000002_extend_current_trials_to_plan_length.php'))->up();
    }

    private function trial(User $provider, string $start, string $end, string $status = 'trialing'): ProviderSubscription
    {
        return ProviderSubscription::create([
            'provider_id' => $provider->id,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'free-trial')->value('id'),
            'status' => $status,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'trial_ends_at' => $end,
        ]);
    }

    public function test_current_and_recent_trials_get_three_months_from_their_start(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2));
        $running = $this->trial(User::factory()->create(['user_type' => 'provider']), '2026-10-20', '2026-11-20');
        $justEnded = $this->trial(User::factory()->create(['user_type' => 'provider']), '2026-09-25', '2026-10-25');

        $this->runMigration();

        $this->assertSame('2027-01-20', $running->fresh()->current_period_end->toDateString());
        $this->assertSame('2027-01-20', $running->fresh()->trial_ends_at->toDateString());
        $this->assertSame('2026-12-25', $justEnded->fresh()->current_period_end->toDateString());
        $this->assertTrue($justEnded->fresh()->is_active);
    }

    public function test_paid_cancelled_and_longer_ones_are_left_alone(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2));
        $paidProvider = User::factory()->create(['user_type' => 'provider']);
        $oldTrial = $this->trial($paidProvider, '2026-09-01', '2026-10-01');
        ProviderSubscription::create([
            'provider_id' => $paidProvider->id,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'monthly')->value('id'),
            'status' => 'active',
            'current_period_start' => '2026-10-01',
            'current_period_end' => '2026-11-01',
        ]);
        $cancelled = $this->trial(User::factory()->create(['user_type' => 'provider']), '2026-10-20', '2026-11-20', 'canceled');
        $alreadyLonger = $this->trial(User::factory()->create(['user_type' => 'provider']), '2026-10-20', '2027-06-01');

        $this->runMigration();

        $this->assertSame('2026-10-01', $oldTrial->fresh()->current_period_end->toDateString());
        $this->assertSame('2026-11-20', $cancelled->fresh()->current_period_end->toDateString());
        $this->assertSame('2027-06-01', $alreadyLonger->fresh()->current_period_end->toDateString());
    }
}
