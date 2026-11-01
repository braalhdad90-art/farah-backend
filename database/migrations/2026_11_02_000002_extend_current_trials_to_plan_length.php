<?php

use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// فرح: المزودين اللي بدأوا الفترة المجانية لما كانت شهر، بياخدوا مدة الباقة الجديدة (3 أشهر) من تاريخ بدايتهم.
// يمدّد فقط (ما بيقصّر)، وما بيلمس اللي انتقل لباقة مدفوعة أو ألغى.
return new class extends Migration
{
    public function up(): void
    {
        $plan = SubscriptionPlan::where('slug', 'free-trial')->first();
        if (! $plan) {
            return;
        }

        $trials = DB::table('provider_subscriptions')
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'trialing')
            ->whereNotNull('current_period_start')
            ->get();

        foreach ($trials as $trial) {
            $hasLater = DB::table('provider_subscriptions')
                ->where('provider_id', $trial->provider_id)
                ->where('id', '>', $trial->id)
                ->exists();
            if ($hasLater) {
                continue;
            }

            $newEnd = $plan->periodEndFrom(Carbon::parse($trial->current_period_start));
            if ($trial->current_period_end && $newEnd->lte(Carbon::parse($trial->current_period_end))) {
                continue;
            }

            DB::table('provider_subscriptions')->where('id', $trial->id)->update([
                'current_period_end' => $newEnd,
                'trial_ends_at' => $newEnd,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Extensions are kept; providers were told about the longer trial.
    }
};
