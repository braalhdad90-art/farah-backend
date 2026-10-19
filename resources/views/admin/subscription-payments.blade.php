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
            <thead><tr><th>المزود</th><th>النشاط</th><th>الباقة</th><th>المبلغ</th><th>تاريخ الإرسال</th><th></th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td class="fw-bold">{{ $payment->provider?->name ?? '—' }}</td>
                    <td>{{ $payment->provider?->providerProfile?->business_name ?? '—' }}</td>
                    <td>{{ $payment->plan?->name ?? '—' }}</td>
                    <td class="fw-bold">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                    <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                    <td>
                        @if($payment->isLockedForOthers(auth()->id()))
                            <span class="badge text-bg-warning mb-1 d-inline-block"><i class="fa-solid fa-lock ms-1"></i>يراجعه {{ $payment->reviewer?->name }}</span>
                        @endif
                        <a class="btn btn-sm btn-primary" href="{{ route('admin.finance.subscription.show', $payment) }}"><i class="fa-solid fa-magnifying-glass ms-1"></i>مراجعة</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-5 text-center text-muted"><i class="fa-solid fa-circle-check fs-4 d-block mb-2"></i>لا توجد إشعارات تحويل بانتظار المراجعة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
