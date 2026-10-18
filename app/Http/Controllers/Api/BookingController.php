<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Api\Concerns\ImageUrlHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelBookingRequest;
use App\Http\Requests\Api\CreateBookingRequest;
use App\Http\Requests\Api\ProviderBookingDecisionRequest;
use App\Http\Requests\Api\ReviewBookingPaymentRequest;
use App\Http\Requests\Api\SubmitBookingPaymentRequest;
use App\Http\Requests\Api\UpdateProviderPaymentInstructionsRequest;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Service;
use App\Services\BookingExpirationService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Bookings', description: 'إدارة حجوزات العملاء ومزودي الخدمة والمدفوعات')]
class BookingController extends Controller
{
    use ApiResponse, ImageUrlHelper;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly BookingExpirationService $expiration,
    ) {}

    #[OA\Post(path: '/api/services/{id}/bookings', summary: 'إنشاء طلب حجز', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['booking_date', 'booking_time'], properties: [new OA\Property(property: 'booking_date', type: 'string', format: 'date'), new OA\Property(property: 'booking_time', type: 'string'), new OA\Property(property: 'booking_slot', type: 'string', nullable: true), new OA\Property(property: 'notes', type: 'string', nullable: true)])), responses: [new OA\Response(response: 201, description: 'تم إرسال طلب الحجز'), new OA\Response(response: 422, description: 'الفترة غير متاحة')])]
    public function store(CreateBookingRequest $request, int $id)
    {
        $this->expiration->expireStale();

        $booking = DB::transaction(function () use ($request, $id) {
            $service = Service::with(['images', 'items'])->lockForUpdate()
                ->where('status', 'active')->where('is_available', true)->forApprovedProviders()->findOrFail($id);
            $slot = $this->resolveSlot($service, $request->booking_time, $request->booking_slot);

            $dateBlocked = $service->unavailableDates()->whereDate('unavailable_date', $request->booking_date)->exists();
            if (! $slot || $dateBlocked || $this->slotTaken($service->id, $request->booking_date, $request->booking_time, $request->booking_slot)) {
                throw ValidationException::withMessages(['booking_slot' => 'الفترة المختارة غير متاحة للحجز.']);
            }

            $price = (float) ($slot['price'] ?? $service->price);
            $deposit = $service->deposit_amount !== null
                ? (float) $service->deposit_amount
                : round($price * ((int) ($service->deposit_percentage ?? 20)) / 100, 2);
            $deposit = min($deposit, $price);

            return Booking::create([
                'reference' => $this->reference('BKG'),
                'user_id' => $request->user()->id,
                'service_id' => $service->id,
                'provider_id' => $service->provider_id,
                'booking_date' => $request->booking_date,
                'booking_time' => $request->booking_time,
                'booking_slot' => $request->booking_slot ?: ($slot['label'] ?? null),
                'total_price' => $price,
                'selected_price' => $price,
                'deposit_amount' => $deposit,
                'remaining_amount' => max(0, $price - $deposit),
                'status' => 'pending',
                'workflow_status' => 'provider_pending',
                'expires_at' => now()->addDay(),
                'notes' => $request->notes,
                'service_snapshot' => $this->serviceSnapshot($service, $slot),
            ])->load(['service.images', 'service.items', 'provider.providerProfile', 'payments']);
        });

        $this->notifications->send($booking->provider_id, 'booking_requested', 'طلب حجز جديد', "لديك طلب حجز جديد للخدمة {$booking->service->title}.", 'booking', $booking->id, ['reference' => $booking->reference]);

        return $this->success('تم إرسال طلب الحجز إلى مزود الخدمة.', $this->bookingData($booking), 201);
    }

    #[OA\Get(path: '/api/bookings', summary: 'قائمة حجوزات العميل', security: [['bearerAuth' => []]], tags: ['Bookings'], responses: [new OA\Response(response: 200, description: 'قائمة الحجوزات'), new OA\Response(response: 401, description: 'غير مصرح')])]
    public function customerIndex(Request $request)
    {
        $this->expiration->expireStale();
        $bookings = Booking::with(['service.images', 'provider.providerProfile', 'payments'])
            ->where('user_id', $request->user()->id)->latest()->paginate(min(max((int) $request->get('per_page', 20), 1), 50));

        return $this->successPaginated($bookings, fn (Booking $booking) => $this->bookingData($booking));
    }

    #[OA\Get(path: '/api/provider/bookings', summary: 'قائمة حجوزات مزود الخدمة', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'قائمة الحجوزات'), new OA\Response(response: 401, description: 'غير مصرح')])]
    public function providerIndex(Request $request)
    {
        $this->expiration->expireStale();
        $bookings = Booking::with(['service.images', 'user', 'payments'])
            ->where('provider_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('workflow_status', $request->string('status')->toString()))
            ->latest()->paginate(min(max((int) $request->get('per_page', 20), 1), 50));

        return $this->successPaginated($bookings, fn (Booking $booking) => $this->bookingData($booking));
    }

    #[OA\Get(path: '/api/provider/bookings/{id}', summary: 'تفاصيل حجز لمزود الخدمة', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'تفاصيل الحجز'), new OA\Response(response: 404, description: 'الحجز غير موجود')])]
    public function providerShow(Request $request, int $id)
    {
        $booking = Booking::with(['service.images', 'service.items', 'user', 'payments'])
            ->where('provider_id', $request->user()->id)->findOrFail($id);

        return $this->success('تم جلب تفاصيل الحجز بنجاح.', $this->bookingData($booking));
    }

    #[OA\Get(path: '/api/bookings/{id}', summary: 'تفاصيل حجز العميل', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'تفاصيل الحجز'), new OA\Response(response: 404, description: 'الحجز غير موجود')])]
    #[OA\Get(path: '/api/bookings/{id}/confirmation', summary: 'عرض تأكيد الحجز', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'بيانات تأكيد الحجز'), new OA\Response(response: 404, description: 'الحجز غير موجود')])]
    public function show(Request $request, int $id)
    {
        $booking = Booking::with(['service.images', 'service.items', 'provider.providerProfile', 'payments'])
            ->where('user_id', $request->user()->id)->findOrFail($id);

        return $this->success('تم جلب تفاصيل الحجز بنجاح.', $this->bookingData($booking));
    }

    #[OA\Post(path: '/api/provider/bookings/{id}/decision', summary: 'قبول أو رفض طلب حجز', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['decision', 'deposit_amount'], properties: [new OA\Property(property: 'decision', type: 'string', enum: ['accepted', 'rejected']), new OA\Property(property: 'deposit_amount', type: 'number', format: 'float'), new OA\Property(property: 'provider_note', type: 'string', nullable: true)])), responses: [new OA\Response(response: 200, description: 'تم تحديث حالة الطلب'), new OA\Response(response: 422, description: 'لا يمكن اتخاذ القرار')])]
    public function providerDecision(ProviderBookingDecisionRequest $request, int $id)
    {
        $this->expiration->expireStale();
        $booking = DB::transaction(function () use ($request, $id) {
            $booking = Booking::with('provider.providerProfile')->where('provider_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            if ($booking->workflow_status !== 'provider_pending') {
                throw ValidationException::withMessages(['booking' => 'لا يمكن اتخاذ قرار لهذا الحجز في حالته الحالية.']);
            }

            if ($request->decision === 'rejected') {
                $booking->update([
                    'status' => 'cancelled',
                    'workflow_status' => 'rejected',
                    'provider_note' => $request->provider_note,
                    'cancellation_reason' => $request->provider_note,
                    'expires_at' => null,
                ]);

                return $booking->fresh(['service.images', 'payments']);
            }

            $instructions = trim((string) $booking->provider?->providerProfile?->payment_instructions);
            if ($instructions === '') {
                throw ValidationException::withMessages(['payment_instructions' => 'أدخل تعليمات التحويل الخارجي قبل قبول طلبات الحجز.']);
            }

            $deposit = (float) $request->deposit_amount;
            if ($deposit > (float) $booking->selected_price) {
                throw ValidationException::withMessages(['deposit_amount' => 'العربون لا يمكن أن يتجاوز إجمالي الحجز.']);
            }

            $booking->update([
                'workflow_status' => 'payment_awaiting',
                'deposit_amount' => $deposit,
                'remaining_amount' => (float) $booking->selected_price - $deposit,
                'provider_note' => $request->provider_note,
                'payment_instructions' => $instructions,
                'payment_due_at' => now()->addDay(),
                'expires_at' => now()->addDay(),
            ]);

            return $booking->fresh(['service.images', 'payments']);
        });

        $type = $request->decision === 'accepted' ? 'booking_accepted' : 'booking_rejected';
        $title = $request->decision === 'accepted' ? 'تمت الموافقة على طلب الحجز' : 'تم رفض طلب الحجز';
        $body = $request->decision === 'accepted' ? 'أكمل التحويل الخارجي وارفع إثبات الدفع خلال 24 ساعة.' : ($booking->provider_note ?: 'يمكنك اختيار موعد أو خدمة أخرى.');
        $this->notifications->send($booking->user_id, $type, $title, $body, 'booking', $booking->id, ['reference' => $booking->reference]);

        return $this->success('تم تحديث حالة الحجز بنجاح.', $this->bookingData($booking));
    }

    #[OA\Post(path: '/api/bookings/{id}/payment-proofs', summary: 'رفع إثبات دفع الحجز', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['amount', 'method', 'proof'], properties: [new OA\Property(property: 'amount', type: 'number', format: 'float'), new OA\Property(property: 'method', type: 'string'), new OA\Property(property: 'proof', type: 'string', format: 'binary'), new OA\Property(property: 'customer_note', type: 'string', nullable: true)]))), responses: [new OA\Response(response: 201, description: 'تم رفع إثبات الدفع'), new OA\Response(response: 422, description: 'بيانات الدفع غير صالحة')])]
    public function submitPayment(SubmitBookingPaymentRequest $request, int $id)
    {
        $this->expiration->expireStale();
        $booking = DB::transaction(function () use ($request, $id) {
            $booking = Booking::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            if ($booking->workflow_status !== 'payment_awaiting' || ($booking->payment_due_at && $booking->payment_due_at->isPast())) {
                throw ValidationException::withMessages(['payment' => 'لا يوجد طلب دفع نشط لهذا الحجز.']);
            }
            if (round((float) $request->amount, 2) !== round((float) $booking->deposit_amount, 2)) {
                throw ValidationException::withMessages(['amount' => 'يجب أن يطابق مبلغ التحويل قيمة العربون المطلوبة.']);
            }

            $payment = BookingPayment::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'provider_id' => $booking->provider_id,
                'reference' => $this->reference('PAY'),
                'amount' => $request->amount,
                'method' => $request->method,
                'proof_path' => $request->file('proof')->store('payment-proofs', 'local'),
                'customer_note' => $request->customer_note,
            ]);
            $booking->update(['workflow_status' => 'review_under_proof', 'expires_at' => now()->addDay()]);

            return $booking->fresh(['service.images', 'payments']);
        });

        $this->notifications->send($booking->provider_id, 'payment_proof_submitted', 'إثبات دفع جديد', 'رفع العميل إثبات تحويل للعربون؛ راجعه لتأكيد الحجز.', 'booking', $booking->id, ['reference' => $booking->reference]);

        return $this->success('تم رفع إثبات التحويل وهو الآن قيد مراجعة مزود الخدمة.', $this->bookingData($booking), 201);
    }

    #[OA\Post(path: '/api/provider/bookings/{bookingId}/payments/{paymentId}/review', summary: 'مراجعة إثبات الدفع', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'bookingId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['decision'], properties: [new OA\Property(property: 'decision', type: 'string', enum: ['confirmed', 'rejected']), new OA\Property(property: 'review_note', type: 'string', nullable: true)])), responses: [new OA\Response(response: 200, description: 'تمت مراجعة إثبات الدفع'), new OA\Response(response: 422, description: 'لا يمكن مراجعة الإثبات')])]
    public function reviewPayment(ReviewBookingPaymentRequest $request, int $bookingId, int $paymentId)
    {
        $booking = DB::transaction(function () use ($request, $bookingId, $paymentId) {
            $booking = Booking::where('provider_id', $request->user()->id)->lockForUpdate()->findOrFail($bookingId);
            $payment = BookingPayment::where('booking_id', $booking->id)->where('provider_id', $request->user()->id)->lockForUpdate()->findOrFail($paymentId);
            if ($booking->workflow_status !== 'review_under_proof' || $payment->status !== 'submitted') {
                throw ValidationException::withMessages(['payment' => 'لا يمكن مراجعة هذا الإثبات في حالته الحالية.']);
            }

            $payment->update(['status' => $request->decision, 'review_note' => $request->review_note, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($request->decision === 'confirmed') {
                $booking->update(['status' => 'confirmed', 'workflow_status' => 'confirmed', 'confirmed_at' => now(), 'expires_at' => null]);
            } else {
                $booking->update(['workflow_status' => 'payment_awaiting', 'payment_due_at' => now()->addDay(), 'expires_at' => now()->addDay()]);
            }

            return $booking->fresh(['service.images', 'payments']);
        });

        $confirmed = $request->decision === 'confirmed';
        $this->notifications->send($booking->user_id, $confirmed ? 'payment_confirmed' : 'payment_rejected', $confirmed ? 'تم تأكيد الدفع والحجز' : 'تعذر اعتماد إثبات الدفع', $confirmed ? 'تم تأكيد حجزك بنجاح.' : ($request->review_note ?: 'راجع بيانات التحويل وارفع إثباتًا جديدًا.'), 'booking', $booking->id, ['reference' => $booking->reference]);

        return $this->success('تمت مراجعة إثبات الدفع بنجاح.', $this->bookingData($booking));
    }

    #[OA\Post(path: '/api/bookings/{id}/cancel', summary: 'إلغاء حجز العميل', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', nullable: true)])), responses: [new OA\Response(response: 200, description: 'تم إلغاء الحجز'), new OA\Response(response: 422, description: 'لا يمكن إلغاء الحجز')])]
    public function cancel(CancelBookingRequest $request, int $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($id);
        if (! in_array($booking->workflow_status, ['provider_pending', 'payment_awaiting'], true)) {
            return $this->failure('لا يمكن إلغاء الحجز في حالته الحالية.');
        }
        $booking->update(['status' => 'cancelled', 'workflow_status' => 'cancelled', 'cancellation_reason' => $request->reason, 'expires_at' => null]);
        $this->notifications->send($booking->provider_id, 'booking_cancelled', 'تم إلغاء طلب الحجز', $request->reason ?: 'ألغى العميل طلب الحجز.', 'booking', $booking->id, ['reference' => $booking->reference]);

        return $this->success('تم إلغاء الحجز بنجاح.', $this->bookingData($booking->fresh(['service.images', 'payments'])));
    }

    #[OA\Post(path: '/api/provider/bookings/{id}/complete', summary: 'إكمال الحجز', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'تم إكمال الحجز'), new OA\Response(response: 422, description: 'لا يمكن إكمال الحجز')])]
    public function complete(Request $request, int $id)
    {
        $booking = Booking::where('provider_id', $request->user()->id)->findOrFail($id);
        if ($booking->workflow_status !== 'confirmed' || $booking->booking_date->isFuture()) {
            return $this->failure('لا يمكن إكمال الحجز قبل موعده أو قبل تأكيد الدفع.');
        }
        $booking->update(['status' => 'completed', 'workflow_status' => 'completed', 'completed_at' => now()]);
        $this->notifications->send($booking->user_id, 'booking_completed', 'اكتملت الخدمة', 'يمكنك الآن تقييم الخدمة من صفحة طلباتك.', 'booking', $booking->id, ['reference' => $booking->reference]);

        return $this->success('تم إكمال الحجز بنجاح.', $this->bookingData($booking->fresh(['service.images', 'payments'])));
    }

    #[OA\Put(path: '/api/provider/payment-instructions', summary: 'تحديث تعليمات التحويل', security: [['bearerAuth' => []]], tags: ['Bookings'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['payment_instructions'], properties: [new OA\Property(property: 'payment_instructions', type: 'string')])), responses: [new OA\Response(response: 200, description: 'تم حفظ تعليمات التحويل'), new OA\Response(response: 422, description: 'بيانات المزود غير مكتملة')])]
    public function updatePaymentInstructions(UpdateProviderPaymentInstructionsRequest $request)
    {
        $profile = $request->user()->providerProfile;
        abort_unless($profile, 422, 'أكمل بيانات مزود الخدمة أولاً.');
        $profile->update(['payment_instructions' => $request->payment_instructions]);

        return $this->success('تم حفظ تعليمات التحويل الخارجي.');
    }

    #[OA\Get(path: '/api/provider/bookings/{bookingId}/payments/{paymentId}/proof', summary: 'تحميل إثبات الدفع', security: [['bearerAuth' => []]], tags: ['Bookings'], parameters: [new OA\Parameter(name: 'bookingId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'ملف إثبات الدفع'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'الملف غير موجود')])]
    public function downloadProof(Request $request, int $bookingId, int $paymentId)
    {
        $payment = BookingPayment::where('booking_id', $bookingId)
            ->where(fn ($q) => $q
                ->where('provider_id', $request->user()->id)
                ->orWhere('user_id', $request->user()->id)
            )
            ->findOrFail($paymentId);

        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404, 'ملف الإثبات غير موجود.');

        return Storage::disk('local')->download($payment->proof_path);
    }

    private function slotTaken(int $serviceId, string $date, string $time, ?string $slot): bool
    {
        $normalizedTime = Carbon::createFromFormat('H:i', $time)->format('H:i:s');

        return Booking::query()
            ->where('service_id', $serviceId)
            ->whereDate('booking_date', $date)
            ->whereTime('booking_time', $normalizedTime)
            ->when(filled($slot), fn ($q) => $q->where('booking_slot', $slot))
            ->whereIn('workflow_status', ['provider_pending', 'payment_awaiting', 'review_under_proof', 'confirmed'])
            ->exists();
    }

    private function resolveSlot(Service $service, string $time, ?string $label): ?array
    {
        return collect($service->booking_slots ?: [])->first(fn (array $slot) => ($slot['start_time'] ?? null) === $time && (($slot['label'] ?? null) === $label || blank($label)));
    }

    private function serviceSnapshot(Service $service, array $slot): array
    {
        return ['title' => $service->title, 'description' => $service->description, 'price' => (float) ($slot['price'] ?? $service->price), 'currency' => $service->currency ?: 'ILS', 'slot' => $slot, 'image' => $service->image, 'cancellation_policy' => $service->cancellation_policy];
    }

    private function bookingData(Booking $booking): array
    {
        $service = $booking->service;
        $snapshot = $booking->service_snapshot ?? [];
        $image = $service?->image ?: $service?->images?->first()?->image_path;

        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->workflow_status,
            'legacy_status' => $booking->status,
            'date' => $booking->booking_date?->toDateString(),
            'time' => Carbon::parse($booking->booking_time)->format('H:i'),
            'slot' => $booking->booking_slot,
            'notes' => $booking->notes,
            'provider_note' => $booking->provider_note,
            'customer' => $booking->relationLoaded('user') ? [
                'id' => $booking->user?->id,
                'name' => $booking->user?->name,
                'avatar' => $booking->user?->avatar ? Storage::disk('public')->url($booking->user->avatar) : null,
            ] : null,
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'payment_due_at' => $booking->payment_due_at?->toIso8601String(),
            'payment_instructions' => in_array($booking->workflow_status, ['payment_awaiting', 'review_under_proof', 'confirmed', 'completed'], true) ? $booking->payment_instructions : null,
            'service' => [
                'id' => $service?->id,
                'title' => $service?->title ?? ($snapshot['title'] ?? null),
                'image' => $this->imageUrl($image),
            ],
            'pricing' => ['total' => (float) $booking->selected_price, 'deposit' => (float) $booking->deposit_amount, 'remaining' => (float) $booking->remaining_amount, 'currency' => $service?->currency ?? ($snapshot['currency'] ?? 'ILS')],
            'payments' => $booking->payments?->map(fn (BookingPayment $payment) => ['id' => $payment->id, 'reference' => $payment->reference, 'amount' => (float) $payment->amount, 'method' => $payment->method, 'status' => $payment->status, 'customer_note' => $payment->customer_note, 'review_note' => $payment->review_note, 'created_at' => $payment->created_at?->toIso8601String()])->values(),
        ];
    }

    private function reference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.now()->format('ymd').'-'.strtoupper(Str::random(7));
        } while (Booking::where('reference', $reference)->exists() || BookingPayment::where('reference', $reference)->exists());

        return $reference;
    }
}
