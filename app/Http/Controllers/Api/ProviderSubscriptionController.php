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

    #[OA\Get(path: "/api/provider/subscription", summary: "عرض الاشتراك الحالي وإشعار التحويل قيد المراجعة", security: [["bearerAuth" => []]], tags: ["Provider Subscriptions"], responses: [new OA\Response(response: 200, description: "بيانات الاشتراك الحالي و pending_payment")])]
    public function current(Request $request)
    {
        $subscription = $request->user()->providerSubscriptions()
            ->with('plan')
            ->latest()
            ->first();

        return response()->json([
            'status' => true,
            'data' => $subscription,
            'pending_payment' => $request->user()->subscriptionPayments()
                ->with('plan')
                ->where('status', 'submitted')
                ->latest()
                ->first(),
        ]);
    }

    // فرح: تفعيل الاشتراك. الشهر المجاني يتفعّل فورًا، والباقات المدفوعة تحتاج إشعار تحويل (proof)
    // يراجعه الأدمن من لوحة التحكم: المالية ← اشتراكات المزودين (AdminFinanceController).
    #[OA\Post(
        path: "/api/provider/subscription",
        summary: "تفعيل الشهر المجاني أو إرسال إشعار تحويل لباقة مدفوعة",
        security: [["bearerAuth" => []]],
        tags: ["Provider Subscriptions"],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: "multipart/form-data",
            schema: new OA\Schema(required: ["plan"], properties: [
                new OA\Property(property: "plan", type: "string", example: "monthly"),
                new OA\Property(property: "proof", type: "string", format: "binary", description: "مطلوب للباقات المدفوعة"),
            ])
        )),
        responses: [
            new OA\Response(response: 200, description: "الشهر المجاني مفعّل مسبقًا"),
            new OA\Response(response: 201, description: "تم تفعيل الشهر المجاني أو استلام إشعار التحويل"),
            new OA\Response(response: 409, description: "تم استخدام الشهر المجاني أو يوجد إشعار قيد المراجعة"),
            new OA\Response(response: 422, description: "بيانات غير صالحة"),
        ]
    )]
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'exists:subscription_plans,slug'],
        ]);

        $plan = SubscriptionPlan::where('slug', $validated['plan'])
            ->where('is_active', true)
            ->firstOrFail();

        $provider = $request->user();

        abort_unless(
            $provider->status === 'active'
                && ProviderProfile::where('user_id', $provider->id)->where('status', 'approved')->where('registration_status', 'approved')->exists(),
            403,
            'يجب اعتماد حساب مزود الخدمة قبل تفعيل الاشتراك.'
        );

        return (float) $plan->price > 0
            ? $this->submitPaidPlan($request, $provider, $plan)
            : $this->startFreeTrial($provider, $plan);
    }

    private function startFreeTrial($provider, SubscriptionPlan $trialPlan)
    {
        $now = Carbon::now();

        // Approving a provider already opens the free month, so selecting it again just confirms it.
        $activeTrial = $provider->activeProviderSubscription()->with('plan')->first();
        if ($activeTrial && $activeTrial->status === 'trialing') {
            return response()->json([
                'status' => true,
                'message' => 'الشهر المجاني مفعّل على حسابك.',
                'data' => $activeTrial,
            ]);
        }

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
                'message' => 'تم استخدام الشهر المجاني مسبقًا. اختر باقة مدفوعة للمتابعة.',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تفعيل الشهر المجاني بنجاح.',
            'data' => $subscription->load('plan'),
        ], 201);
    }

    private function submitPaidPlan(Request $request, $provider, SubscriptionPlan $plan)
    {
        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'proof.required' => 'يرجى رفع إشعار التحويل البنكي.',
        ]);

        $payment = DB::transaction(function () use ($request, $provider, $plan) {
            $lockedProvider = $provider->newQuery()->whereKey($provider->id)->lockForUpdate()->firstOrFail();
            if ($lockedProvider->subscriptionPayments()->where('status', 'submitted')->exists()) {
                return null;
            }

            return $lockedProvider->subscriptionPayments()->create([
                'subscription_plan_id' => $plan->id,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'proof_path' => $request->file('proof')->store('subscription-proofs', 'local'),
                'status' => 'submitted',
            ]);
        });

        if (! $payment) {
            return response()->json([
                'status' => false,
                'message' => 'لديك إشعار تحويل قيد المراجعة بالفعل.',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم استلام إشعار التحويل، وسيتم تفعيل اشتراكك بعد مراجعة الإدارة.',
            'data' => $payment->load('plan'),
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
