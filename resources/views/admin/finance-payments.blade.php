@extends('admin.layout')

@section('title', 'دفعات الحجوزات')

@section('content')
{{-- فرح: سجل للاطلاع فقط. المزوّد هو من يعتمد إثبات دفع الحجز من التطبيق. --}}
@php($statusLabels = ['submitted' => ['بانتظار المزوّد', 'text-bg-warning'], 'confirmed' => ['معتمد', 'text-bg-success'], 'rejected' => ['مرفوض', 'text-bg-danger']])
<section class="page-heading">
    <div><h1>سجل دفعات الحجوزات</h1><p>للاطلاع والمتابعة فقط؛ اعتماد إثبات دفع الحجز يتم من المزوّد داخل التطبيق.</p></div>
    <a href="{{ route('admin.finance.refunds') }}" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-rotate-left ms-2"></i>إدارة الاستردادات</a>
</section>

<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small fw-bold">الحالة</label><select name="status" class="form-select">
            <option value="">الكل</option>
            @foreach($statusLabels as $value => [$label])
                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <button class="btn btn-primary">تصفية</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>رقم الدفعة</th><th>العميل</th><th>المزود</th><th>الخدمة</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الرفع</th><th>تاريخ المراجعة</th><th>الإثبات</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                @php([$label, $badge] = $statusLabels[$payment->status] ?? [$payment->status, 'text-bg-secondary'])
                <tr>
                    <td class="fw-bold">{{ $payment->reference }}</td>
                    <td>{{ $payment->customer?->name ?? '—' }}</td>
                    <td>{{ $payment->provider?->name ?? '—' }}</td>
                    <td>{{ $payment->booking?->service?->title ?? '—' }}</td>
                    <td class="fw-bold">{{ number_format($payment->amount, 2) }} {{ $payment->booking?->service?->currency ?? '' }}</td>
                    <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                    <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $payment->reviewed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="{{ Illuminate\Support\Facades\URL::temporarySignedRoute('admin.finance.payment.proof', now()->addMinutes(5), ['payment' => $payment->id]) }}"><i class="fa-regular fa-image ms-1"></i>عرض</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="p-5 text-center text-muted"><i class="fa-solid fa-receipt fs-4 d-block mb-2"></i>لا توجد دفعات.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
