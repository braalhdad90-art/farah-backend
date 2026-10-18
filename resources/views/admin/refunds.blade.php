@extends('admin.layout')

@section('title', 'إدارة الاستردادات')

@section('content')
<section class="page-heading">
    <div><h1>طلبات الاسترداد</h1><p>تابع الطلبات المالية وسجّل قرار المراجعة والتحويل.</p></div>
</section>
<div class="alert alert-warning d-flex gap-2 align-items-start"><i class="fa-solid fa-circle-info mt-1"></i><span>تسجيل الطلب لا يحوّل الأموال تلقائياً. بعد الموافقة، نفّذ التحويل عبر البنك أو مزود الدفع ثم سجّل رقم مرجع العملية.</span></div>

<section class="card mb-4">
    <div class="card-header bg-white py-3"><h2 class="h6 fw-bold mb-0">الحجوزات المؤهلة للاسترداد</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الحجز</th><th>العميل والخدمة</th><th>المدفوع المؤكد</th><th>تسجيل طلب</th></tr></thead>
            <tbody>
            @forelse($eligibleBookings as $booking)
                <tr>
                    <td class="fw-bold">{{ $booking->reference }}<div class="small text-muted">{{ $booking->workflow_status === 'confirmed' ? 'مؤكد' : 'مكتمل' }}</div></td>
                    <td>{{ $booking->user?->name ?? '—' }}<div class="small text-muted">{{ $booking->service?->title ?? '—' }}</div></td>
                    <td>{{ number_format((float) $booking->payments->sum('amount'), 2) }}</td>
                    <td><form method="POST" action="{{ route('admin.finance.refunds.create', $booking) }}" class="d-flex flex-column gap-2">
                        @csrf
                        <input type="number" name="amount" min="0.01" step="0.01" max="{{ $booking->payments->sum('amount') }}" class="form-control form-control-sm" placeholder="مبلغ الاسترداد" required>
                        <input name="reason" class="form-control form-control-sm" placeholder="سبب الطلب" minlength="5" maxlength="2000" required>
                        <button class="btn btn-sm btn-outline-primary">تسجيل الطلب</button>
                    </form></td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-5 text-center text-muted">لا توجد حجوزات مؤهلة حالياً.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="card-header bg-white py-3"><h2 class="h6 fw-bold mb-0">سجل طلبات الاسترداد</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الحجز</th><th>العميل</th><th>المبلغ</th><th>السبب والحالة</th><th>الإجراء</th></tr></thead>
            <tbody>
            @forelse($refunds as $refund)
                @php($refundLabels = ['pending' => 'بانتظار المراجعة', 'approved' => 'موافق عليه', 'rejected' => 'مرفوض', 'processed' => 'تم التحويل'])
                <tr>
                    <td>{{ $refund->booking?->reference ?? '—' }}<div class="small text-muted">{{ $refund->created_at?->format('Y/m/d H:i') }}</div></td>
                    <td>{{ $refund->booking?->user?->name ?? '—' }}</td>
                    <td class="fw-bold">{{ number_format((float) $refund->amount, 2) }}</td>
                    <td>{{ $refund->reason }}<div class="mt-1"><span class="badge text-bg-secondary">{{ $refundLabels[$refund->status] ?? $refund->status }}</span></div>
                        @if($refund->review_note)<div class="small text-muted mt-1">{{ $refund->review_note }}</div>@endif
                        @if($refund->external_reference)<div class="small text-muted mt-1">مرجع التحويل: {{ $refund->external_reference }}</div>@endif
                    </td>
                    <td>
                        @if($refund->status === 'pending')
                            <form method="POST" action="{{ route('admin.finance.refunds.decision', $refund) }}" class="d-flex flex-column gap-2">
                                @csrf
                                <input name="review_note" class="form-control form-control-sm" placeholder="ملاحظة الرفض (مطلوبة عند الرفض)">
                                <div class="d-flex gap-2">
                                    <button name="action" value="approve" class="btn btn-sm btn-success">موافقة</button>
                                    <button name="action" value="reject" class="btn btn-sm btn-outline-danger">رفض</button>
                                </div>
                            </form>
                        @elseif($refund->status === 'approved')
                            <form method="POST" action="{{ route('admin.finance.refunds.decision', $refund) }}" onsubmit="return confirm('تأكد من تنفيذ التحويل خارج النظام قبل التأكيد.')">
                                @csrf
                                <input name="external_reference" class="form-control form-control-sm mb-2" placeholder="رقم مرجع التحويل" required minlength="3" maxlength="120">
                                <button name="action" value="mark_processed" class="btn btn-sm btn-primary">تسجيل إتمام التحويل</button>
                            </form>
                        @else<span class="text-muted">لا يوجد إجراء</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-5 text-center text-muted">لم تُسجّل طلبات استرداد بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="mt-3">{{ $refunds->links() }}</div>
@endsection
