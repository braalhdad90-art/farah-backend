@extends('admin.layout')

@section('title', 'مراجعة الدفعات')

@section('content')
<section class="page-heading">
    <div><h1>مراجعة إثباتات الدفع</h1><p>تحقق من التحويلات المرسلة؛ اعتماد الدفعة يؤكد الحجز، ورفضها يعيد الطلب للدفع.</p></div>
    <a href="{{ route('admin.finance.refunds') }}" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-rotate-left ms-2"></i>إدارة الاستردادات</a>
</section>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>رقم الدفعة</th><th>العميل</th><th>المزود</th><th>الخدمة</th><th>المبلغ</th><th>الإثبات</th><th>القرار</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td class="fw-bold">{{ $payment->reference }}</td>
                    <td>{{ $payment->customer?->name ?? '—' }}</td>
                    <td>{{ $payment->provider?->name ?? '—' }}</td>
                    <td>{{ $payment->booking?->service?->title ?? '—' }}</td>
                    <td class="fw-bold">{{ number_format($payment->amount, 2) }} {{ $payment->booking?->service?->currency ?? '' }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="{{ Illuminate\Support\Facades\URL::temporarySignedRoute('admin.finance.payment.proof', now()->addMinutes(5), ['payment' => $payment->id]) }}"><i class="fa-regular fa-image ms-1"></i>عرض الإثبات · ٥ دقائق</a></td>
                    <td>
                        <form method="POST" action="{{ route('admin.finance.payment.review', $payment) }}" class="d-flex flex-column gap-2">
                            @csrf
                            <textarea name="review_note" class="form-control form-control-sm" rows="2" placeholder="ملاحظة الرفض (مطلوبة عند الرفض)"></textarea>
                            <div class="d-flex gap-2">
                                <button name="decision" value="confirmed" class="btn btn-sm btn-success"><i class="fa-solid fa-check ms-1"></i>اعتماد</button>
                                <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark ms-1"></i>رفض</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-5 text-center text-muted"><i class="fa-solid fa-circle-check fs-4 d-block mb-2"></i>لا توجد إثباتات دفع بانتظار المراجعة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
