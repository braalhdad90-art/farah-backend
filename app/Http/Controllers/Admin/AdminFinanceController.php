<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminRefund;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ProviderPayout;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminFinanceController extends Controller
{
    // فرح: أسباب رفض إشعار تحويل الاشتراك (ADM-23). "other" يتطلب كتابة ملاحظة.
    public const SUBSCRIPTION_REJECTION_REASONS = [
        'not_received' => 'لم يصل التحويل إلى حساب المنصة',
        'amount_mismatch' => 'المبلغ المحوّل لا يطابق سعر الباقة',
        'unreadable' => 'صورة الإشعار غير واضحة',
        'other' => 'سبب آخر',
    ];

    // فرح: سجل دفعات الحجوزات للاطلاع فقط. اعتماد إثبات دفع الحجز من صلاحية المزوّد في التطبيق، لا الإدارة.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:submitted,confirmed,rejected'],
        ]);

        $payments = BookingPayment::with(['booking.service', 'customer', 'provider'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.finance-payments', ['payments' => $payments, 'filters' => $filters]);
    }

    public function proof(BookingPayment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404, 'إثبات الدفع غير موجود.');

        return Storage::disk('local')->response(
            $payment->proof_path,
            'payment-proof-'.$payment->id.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store, max-age=0']
        );
    }

    // فرح: مراجعة إشعارات تحويل اشتراكات المزودين. الاعتماد يفعّل الباقة، والرفض يرجع المزود لرفع إشعار جديد.
    public function subscriptionPayments()
    {
        $payments = SubscriptionPayment::with(['provider.providerProfile', 'plan', 'reviewer'])
            ->where('status', 'submitted')
            ->latest()
            ->paginate(20);

        return view('admin.subscription-payments', ['payments' => $payments]);
    }

    // ADM-21/30: opening a proof holds it for this reviewer for 15 minutes.
    public function showSubscriptionPayment(SubscriptionPayment $payment)
    {
        $payment = DB::transaction(function () use ($payment): SubscriptionPayment {
            $locked = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'submitted' && ! $locked->isLockedForOthers(Auth::id())) {
                $locked->update(['review_locked_by' => Auth::id(), 'review_locked_until' => now()->addMinutes(15)]);
            }

            return $locked->fresh(['provider.providerProfile', 'plan', 'reviewer']);
        });

        return view('admin.subscription-payment', [
            'payment' => $payment,
            'lockedByOther' => $payment->isLockedForOthers(Auth::id()),
        ]);
    }

    public function subscriptionProof(SubscriptionPayment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404, 'إشعار التحويل غير موجود.');

        return Storage::disk('local')->response(
            $payment->proof_path,
            'subscription-proof-'.$payment->id.'.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store, max-age=0']
        );
    }

    public function reviewSubscriptionPayment(Request $request, SubscriptionPayment $payment, NotificationService $notifications)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:confirmed,rejected'],
            'reason' => ['nullable', 'required_if:decision,rejected', 'in:'.implode(',', array_keys(self::SUBSCRIPTION_REJECTION_REASONS))],
            'review_note' => ['nullable', 'string', 'min:5', 'max:2000', 'required_if:reason,other'],
        ]);
        if ($data['decision'] === 'rejected') {
            $data['review_note'] = trim(implode(' — ', array_filter([
                $data['reason'] === 'other' ? null : self::SUBSCRIPTION_REJECTION_REASONS[$data['reason']],
                $data['review_note'] ?? null,
            ])));
        }

        DB::transaction(function () use ($payment, $data, $notifications, $request): void {
            $locked = SubscriptionPayment::with('plan')->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'submitted', 409, 'تمت مراجعة إشعار التحويل مسبقًا.');
            abort_if($locked->isLockedForOthers(Auth::id()), 423, 'يراجع زميل آخر هذا الإشعار الآن.');

            $before = $locked->only(['status', 'review_note', 'reviewed_by', 'reviewed_at', 'provider_subscription_id']);
            $confirmed = $data['decision'] === 'confirmed';

            $subscriptionId = null;
            if ($confirmed) {
                // Remaining days of the current period carry over to the new plan.
                $current = ProviderSubscription::where('provider_id', $locked->provider_id)
                    ->whereIn('status', ['trialing', 'active'])
                    ->where('current_period_end', '>', now())
                    ->lockForUpdate()
                    ->get();
                $start = now();
                $base = $current->max('current_period_end') ?? $start;
                $count = max(1, (int) $locked->plan->interval_count);
                $end = $locked->plan->interval === 'year'
                    ? $base->copy()->addYearsNoOverflow($count)
                    : $base->copy()->addMonthsNoOverflow($count);

                ProviderSubscription::whereKey($current->pluck('id'))->update(['status' => 'replaced']);
                $subscriptionId = ProviderSubscription::create([
                    'provider_id' => $locked->provider_id,
                    'subscription_plan_id' => $locked->subscription_plan_id,
                    'status' => 'active',
                    'current_period_start' => $start,
                    'current_period_end' => $end,
                ])->id;
            }

            $locked->update([
                'status' => $data['decision'],
                'review_note' => $data['review_note'] ?? null,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'provider_subscription_id' => $subscriptionId,
                'review_locked_by' => null,
                'review_locked_until' => null,
            ]);

            AdminAuditLog::create([
                'actor_id' => Auth::id(),
                'user_id' => $locked->provider_id,
                'actor_role' => Auth::user()?->getRoleNames()->first(),
                'action' => 'subscription_payment_'.$data['decision'],
                'resource_type' => 'subscription_payment',
                'resource_id' => $locked->id,
                'before' => $before,
                'after' => $locked->fresh()->only(array_keys($before)),
                'ip_address' => $request->ip(),
            ]);

            $notifications->send(
                $locked->provider_id,
                $confirmed ? 'subscription_activated' : 'subscription_payment_rejected',
                $confirmed ? 'تم تفعيل اشتراكك' : 'تعذر اعتماد إشعار التحويل',
                $confirmed ? 'تم اعتماد التحويل وتفعيل '.$locked->plan->name.'.' : ($data['review_note'] ?? 'راجع بيانات التحويل وارفع إشعارًا جديدًا.'),
                'subscription_payment',
                $locked->id
            );
        });

        return redirect()->route('admin.finance.subscriptions')->with('success', 'تمت مراجعة إشعار التحويل.');
    }

    // فرح: تحويلات المنصة إلى المزودين (ADM-26/31).
    public function payouts()
    {
        return view('admin.payouts', [
            'payouts' => ProviderPayout::with(['provider.providerProfile', 'booking', 'recorder'])->latest('paid_at')->latest('id')->paginate(20),
            'providers' => User::where('user_type', 'provider')
                ->whereHas('providerProfile', fn ($query) => $query->where('status', 'approved'))
                ->with('providerProfile:id,user_id,business_name')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function createPayout(Request $request, NotificationService $notifications)
    {
        $data = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:users,id'],
            'booking_reference' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'currency' => ['required', 'alpha', 'size:3'],
            'method' => ['required', 'in:bank_transfer,wallet,cash'],
            'reference' => ['required', 'string', 'min:3', 'max:120'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $provider = User::where('user_type', 'provider')->findOrFail($data['provider_id']);
        $bookingId = null;
        if (! empty($data['booking_reference'])) {
            $bookingId = Booking::where('reference', trim($data['booking_reference']))
                ->where('provider_id', $provider->id)
                ->value('id');
            if (! $bookingId) {
                throw ValidationException::withMessages(['booking_reference' => 'لا يوجد حجز بهذا الرقم لهذا المزود.']);
            }
        }

        $payout = DB::transaction(function () use ($request, $data, $provider, $bookingId): ProviderPayout {
            $payout = ProviderPayout::create([
                'provider_id' => $provider->id,
                'booking_id' => $bookingId,
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency']),
                'method' => $data['method'],
                'reference' => trim($data['reference']),
                'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null,
                'recorded_by' => Auth::id(),
            ]);

            AdminAuditLog::create([
                'actor_id' => Auth::id(),
                'user_id' => $provider->id,
                'actor_role' => Auth::user()?->getRoleNames()->first(),
                'action' => 'payout_recorded',
                'resource_type' => 'provider_payout',
                'resource_id' => $payout->id,
                'before' => null,
                'after' => $payout->only(['amount', 'currency', 'method', 'reference', 'booking_id']) + ['paid_at' => $data['paid_at']],
                'ip_address' => $request->ip(),
            ]);

            return $payout;
        });

        $notifications->send(
            $provider,
            'payout_recorded',
            'تم تحويل مستحقاتك',
            'حوّلت المنصة '.number_format((float) $payout->amount, 2).' '.$payout->currency.' إلى حسابك. رقم العملية: '.$payout->reference,
            'provider_payout',
            $payout->id
        );

        return back()->with('success', 'تم تسجيل التحويل للمزود.');
    }

    public function refunds()
    {
        $refunds = AdminRefund::with(['booking.user', 'booking.service', 'payment', 'requester', 'reviewer'])
            ->latest()
            ->paginate(20);
        $eligibleBookings = Booking::with(['user:id,name', 'service:id,title', 'payments' => fn ($query) => $query->where('status', 'confirmed')])
            ->where('workflow_status', 'confirmed')
            ->whereHas('payments', fn ($query) => $query->where('status', 'confirmed'))
            ->latest()
            ->limit(100)
            ->get();

        return view('admin.refunds', compact('refunds', 'eligibleBookings'));
    }

    public function createRefund(Request $request, Booking $booking, NotificationService $notifications)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $refund = DB::transaction(function () use ($request, $booking, $data): AdminRefund {
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            // BR-9: لا استرداد لحجز مكتمل.
            abort_unless($lockedBooking->workflow_status === 'confirmed', 409, 'لا يمكن الاسترداد إلا للحجوزات المؤكدة التي لم تكتمل بعد.');

            $paidTotal = BookingPayment::where('booking_id', $lockedBooking->id)->where('status', 'confirmed')->sum('amount');
            $reservedRefunds = AdminRefund::where('booking_id', $lockedBooking->id)
                ->whereIn('status', ['pending', 'approved', 'processed'])
                ->sum('amount');
            if ((float) $data['amount'] > (float) $paidTotal - (float) $reservedRefunds) {
                throw ValidationException::withMessages(['amount' => 'يتجاوز مبلغ الاسترداد الرصيد المتبقي من الدفعات المؤكدة.']);
            }

            $refund = AdminRefund::create([
                'booking_id' => $lockedBooking->id,
                'requested_by' => Auth::id(),
                'amount' => $data['amount'],
                'status' => 'pending',
                'reason' => trim($data['reason']),
            ]);
            $this->writeRefundAudit('refund_requested', $refund, null, $refund->only(['amount', 'status', 'reason']), $request);

            return $refund;
        });

        $notifications->send($refund->booking->user_id, 'refund_requested', 'تم تسجيل طلب استرداد', 'تم تسجيل طلب استرداد الحجز للمراجعة.', 'booking', $refund->booking_id);

        return back()->with('success', 'تم تسجيل طلب الاسترداد. لم يتم تحويل أي مبلغ.');
    }

    public function refundDecision(Request $request, AdminRefund $refund, NotificationService $notifications)
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,mark_processed'],
            'review_note' => ['nullable', 'string', 'min:5', 'max:2000', 'required_if:action,reject'],
            'external_reference' => ['nullable', 'string', 'min:3', 'max:120', 'required_if:action,mark_processed'],
        ]);

        $lockedRefund = DB::transaction(function () use ($request, $refund, $data): AdminRefund {
            $locked = AdminRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['status', 'review_note', 'external_reference', 'reviewed_by', 'reviewed_at', 'processed_at']);

            if ($data['action'] === 'approve') {
                abort_unless($locked->status === 'pending', 409, 'لا يمكن الموافقة إلا على طلب استرداد قيد المراجعة.');
                abort_if($locked->requested_by === Auth::id(), 403, 'لا يمكن لمقدم طلب الاسترداد الموافقة على طلبه بنفسه.');
                abort_if(Booking::whereKey($locked->booking_id)->value('workflow_status') === 'completed', 409, 'اكتمل الحجز، فلا يمكن الموافقة على استرداده.');
                $locked->update(['status' => 'approved', 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
            } elseif ($data['action'] === 'reject') {
                abort_unless($locked->status === 'pending', 409, 'لا يمكن رفض إلا طلب استرداد قيد المراجعة.');
                abort_if($locked->requested_by === Auth::id(), 403, 'لا يمكن لمقدم طلب الاسترداد رفض طلبه بنفسه.');
                $locked->update(['status' => 'rejected', 'review_note' => trim($data['review_note']), 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
            } else {
                abort_unless($locked->status === 'approved', 409, 'لا يمكن تسجيل التحويل إلا لطلبات الاسترداد الموافق عليها.');
                abort_if($locked->reviewed_by === Auth::id(), 403, 'لا يمكن لمن وافق على الاسترداد تسجيل التحويل كمكتمل.');
                $locked->update(['status' => 'processed', 'external_reference' => trim($data['external_reference']), 'processed_at' => now()]);
            }

            $this->writeRefundAudit('refund_'.$data['action'], $locked, $before, $locked->fresh()->only(['status', 'review_note', 'external_reference', 'reviewed_by', 'reviewed_at', 'processed_at']), $request);

            return $locked->fresh();
        });

        $notifications->send(
            $lockedRefund->booking->user_id,
            'refund_'.$lockedRefund->status,
            'تحديث طلب الاسترداد',
            $lockedRefund->status === 'processed'
                ? 'تم تسجيل الاسترداد كمحوّل بعد إتمام التحويل خارج النظام.'
                : ($lockedRefund->review_note ?? 'تم تحديث حالة طلب الاسترداد.'),
            'booking',
            $lockedRefund->booking_id
        );

        return back()->with('success', 'تم تحديث حالة طلب الاسترداد.');
    }

    private function writeRefundAudit(string $action, AdminRefund $refund, ?array $before, ?array $after, Request $request): void
    {
        AdminAuditLog::create([
            'actor_id' => Auth::id(),
            'user_id' => $refund->booking?->user_id,
            'actor_role' => Auth::user()?->getRoleNames()->first(),
            'action' => $action,
            'resource_type' => 'admin_refund',
            'resource_id' => $refund->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
        ]);
    }
}
