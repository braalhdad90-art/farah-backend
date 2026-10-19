@extends('admin.layout')

@section('title', 'مراجعة إشعار تحويل')

@section('content')
{{-- فرح: مراجعة إشعار تحويل اشتراك واحد (ADM-30). فتح الصفحة يحجز الإشعار لك 15 دقيقة. --}}
@php($proofUrl = Illuminate\Support\Facades\URL::temporarySignedRoute('admin.finance.subscription.proof', now()->addMinutes(15), ['payment' => $payment->id]))
<section class="page-heading">
    <div><h1>مراجعة إشعار تحويل اشتراك</h1><p>تحقق من أن المبلغ وصل إلى حساب المنصة قبل الاعتماد.</p></div>
    <a href="{{ route('admin.finance.subscriptions') }}" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-right ms-2"></i>العودة للقائمة</a>
</section>

@if($lockedByOther)
    <div class="alert alert-warning"><i class="fa-solid fa-lock ms-2"></i>يراجع {{ $payment->reviewer?->name }} هذا الإشعار الآن (حتى {{ $payment->review_locked_until?->format('H:i') }}). لا يمكنك اتخاذ قرار عليه في الوقت نفسه.</div>
@elseif($payment->status !== 'submitted')
    <div class="alert alert-info">تمت مراجعة هذا الإشعار مسبقًا.</div>
@else
    <div class="alert alert-info"><i class="fa-regular fa-clock ms-2"></i>هذا الإشعار محجوز لك حتى {{ $payment->review_locked_until?->format('H:i') }}.</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body p-3 text-center">
                @if(str_ends_with(strtolower($payment->getRawOriginal('proof_path')), '.pdf'))
                    <a class="btn btn-outline-primary my-5" target="_blank" rel="noopener" href="{{ $proofUrl }}"><i class="fa-regular fa-file-pdf ms-2"></i>فتح ملف الإشعار (PDF)</a>
                @else
                    <a href="{{ $proofUrl }}" target="_blank" rel="noopener"><img src="{{ $proofUrl }}" alt="إشعار التحويل" class="img-fluid rounded border" style="max-height: 70vh"></a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body p-4">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">المزود</dt><dd class="col-7 fw-bold">{{ $payment->provider?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">النشاط</dt><dd class="col-7">{{ $payment->provider?->providerProfile?->business_name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">الباقة</dt><dd class="col-7">{{ $payment->plan?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">المبلغ المطلوب</dt><dd class="col-7 fw-bold">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</dd>
                    <dt class="col-5 text-muted">رقم الإشعار</dt><dd class="col-7">#{{ $payment->id }}</dd>
                    <dt class="col-5 text-muted">تاريخ الرفع</dt><dd class="col-7">{{ $payment->created_at?->format('Y-m-d H:i') }}</dd>
                </dl>
            </div>
        </div>
        @if($payment->status === 'submitted' && ! $lockedByOther)
            <form method="POST" action="{{ route('admin.finance.subscription.review', $payment) }}" class="card">
                @csrf
                <div class="card-body p-4">
                    <label class="form-label small fw-bold">سبب الرفض (عند الرفض)</label>
                    <select name="reason" class="form-select mb-2">
                        <option value="">—</option>
                        @foreach(App\Http\Controllers\Admin\AdminFinanceController::SUBSCRIPTION_REJECTION_REASONS as $value => $label)
                            <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="form-label small fw-bold">ملاحظة للمزود (مطلوبة مع "سبب آخر")</label>
                    <textarea name="review_note" class="form-control mb-3" rows="3">{{ old('review_note') }}</textarea>
                    <div class="d-flex gap-2">
                        <button name="decision" value="confirmed" class="btn btn-success"><i class="fa-solid fa-check ms-1"></i>اعتماد وتفعيل</button>
                        <button name="decision" value="rejected" class="btn btn-outline-danger"><i class="fa-solid fa-xmark ms-1"></i>رفض</button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
