@extends('admin.layout')

@section('title', 'اشتراكات المزودين')

@section('content')
{{-- فرح: إشعارات تحويل اشتراكات المزودين المرسلة من شاشة تفعيل الاشتراك بالتطبيق. --}}
<section class="page-heading">
    <div><h1>إشعارات تحويل الاشتراكات</h1><p>اعتماد الإشعار يفعّل باقة المزود مع إضافة الأيام المتبقية من اشتراكه الحالي، ورفضه يطلب منه رفع إشعار جديد.</p></div>
</section>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>المزود</th><th>النشاط</th><th>الباقة</th><th>المبلغ</th><th>تاريخ الإرسال</th><th>الإشعار</th><th>القرار</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td class="fw-bold">{{ $payment->provider?->name ?? '—' }}</td>
                    <td>{{ $payment->provider?->providerProfile?->business_name ?? '—' }}</td>
                    <td>{{ $payment->plan?->name ?? '—' }}</td>
                    <td class="fw-bold">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                    <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="{{ Illuminate\Support\Facades\URL::temporarySignedRoute('admin.finance.subscription.proof', now()->addMinutes(5), ['payment' => $payment->id]) }}"><i class="fa-regular fa-image ms-1"></i>عرض الإشعار · ٥ دقائق</a></td>
                    <td>
                        <form method="POST" action="{{ route('admin.finance.subscription.review', $payment) }}" class="d-flex flex-column gap-2">
                            @csrf
                            <textarea name="review_note" class="form-control form-control-sm" rows="2" placeholder="ملاحظة الرفض (مطلوبة عند الرفض)"></textarea>
                            <div class="d-flex gap-2">
                                <button name="decision" value="confirmed" class="btn btn-sm btn-success"><i class="fa-solid fa-check ms-1"></i>اعتماد وتفعيل</button>
                                <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark ms-1"></i>رفض</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-5 text-center text-muted"><i class="fa-solid fa-circle-check fs-4 d-block mb-2"></i>لا توجد إشعارات تحويل بانتظار المراجعة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
