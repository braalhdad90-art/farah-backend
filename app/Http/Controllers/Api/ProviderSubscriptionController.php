<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderSubscription;
use App\Models\ProviderProfile;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Provider Subscriptions", description: "إدارة اشتراكات مزود الخدمة")]
class ProviderSubscriptionController extends Controller
{
    #[OA\Get(path: "/api/provider/subscription-plans", summary: "عرض باقات الاشتراك", security: [["bearerAuth" => []]], tags: ["Provider Subscriptions"], responses: [new OA\Response(response: 200, description: "قائمة الباقات")])]
    public function plans()
    {
        return response()->json([
            'status' => true,
            'data' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get(),
        ]);
    }

    #[OA\Get(path: "/api/provider/subscription", summary: "عرض الاشتراك الحالي", security: [["bearerAuth" => []]], tags: ["Provider Subscriptions"], responses: [new OA\Response(response: 200, description: "بيانات الاشتراك الحالي")])]
    public function current(Request $request)
    {
        $subscription = $request->user()->providerSubscriptions()
            ->with('plan')
            ->latest()
            ->first();

        return response()->json([
            'status' => true,
            'data' => $subscription,
        ]);
    }

    #[OA\Post(path: "/api/provider/subscription", summary: "تفعيل اشتراك مزود الخدمة", security: [["bearerAuth" => []]], tags: ["Provider Subscriptions"], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["plan"], properties: [new OA\Property(property: "plan", type: "string", example: "free-trial")])), responses: [new OA\Response(response: 201, description: "تم تفعيل الاشتراك"), new OA\Response(response: 409, description: "تم استخدام الاشتراك المجاني مسبقًا")])]
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'exists:subscription_plans,slug'],
        ]);

        // Keep accepting the plan chosen in the app so Flutter can preserve the
        // existing screen flow. At launch, however, every first subscription is
        // one free month; paid plans will be enabled in a later release.
        SubscriptionPlan::where('slug', $validated['plan'])
            ->where('is_active', true)
            ->firstOrFail();

        $trialPlan = SubscriptionPlan::where('slug', 'free-trial')
            ->where('is_active', true)
            ->firstOrFail();

        $provider = $request->user();
        $now = Carbon::now();

        abort_unless(
            $provider->status === 'active'
                && ProviderProfile::where('user_id', $provider->id)->where('status', 'approved')->where('registration_status', 'approved')->exists(),
            403,
            'يجب اعتماد حساب مزود الخدمة قبل تفعيل الاشتراك.'
        );

        $subscription = DB::transaction(function () use ($provider, $trialPlan, $now) {
            $lockedProvider = $provider->newQuery()->whereKey($provider->id)->lockForUpdate()->firstOrFail();
            if ($lockedProvider->providerSubscriptions()->exists()) {
                return null;
            }

            $trialEndsAt = $now->copy()->addMonthNoOverflow();
            return $lockedProvider->providerSubscriptions()->create([
                'subscription_plan_id' => $trialPlan->id,
                'status' => 'trialing',
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => $now,
                'current_period_end' => $trialEndsAt,
            ]);
        });

        if (! $subscription) {
            return response()->json([
                'status' => false,
                'message' => 'تم استخدام الشهر المجاني مسبقًا. ستتوفر الباقات المدفوعة في تحديث لاحق.',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تفعيل الشهر المجاني بنجاح. الباقات المدفوعة ستتوفر في تحديث لاحق.',
            'data' => $subscription->load('plan'),
        ], 201);
    }

    #[OA\Post(path: "/api/provider/subscription/cancel", summary: "إلغاء اشتراك مزود الخدمة", security: [["bearerAuth" => []]], tags: ["Provider Subscriptions"], responses: [new OA\Response(response: 200, description: "تم إلغاء الاشتراك"), new OA\Response(response: 404, description: "لا يوجد اشتراك فعال")])]
    public function cancel(Request $request)
    {
        $subscription = $request->user()->providerSubscriptions()
            ->whereIn('status', ['trialing', 'active'])
            ->where('current_period_end', '>', now())
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'status' => false,
                'message' => 'لا يوجد اشتراك فعال لإلغائه.',
            ], 404);
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إلغاء الاشتراك.',
            'data' => $subscription->load('plan'),
        ]);
    }
}
