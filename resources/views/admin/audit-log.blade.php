@extends('admin.layout')

@section('title', 'سجل التدقيق')

@push('styles')
    <link href="{{ asset('css/admin-audit-log.css') }}" rel="stylesheet">
@endpush

@php
    $actionLabels = [
        'provider_approved' => 'اعتماد مزود',
        'provider_rejected' => 'رفض مزود',
        'provider_suspended' => 'إيقاف مزود',
        'provider_reactivated' => 'إعادة تفعيل مزود',
        'payment_proof_confirmed' => 'اعتماد إثبات دفع',
        'payment_proof_rejected' => 'رفض إثبات دفع',
        'review_hidden' => 'إخفاء تقييم',
        'review_restored' => 'إعادة إظهار تقييم',
        'service_hidden' => 'إخفاء خدمة',
        'service_restored' => 'إعادة إظهار خدمة',
        'admin_account_updated' => 'تحديث صلاحيات مسؤول',
        'category_created' => 'إضافة تصنيف',
        'category_updated' => 'تحديث تصنيف',
        'subscription_plan_updated' => 'تحديث باقة اشتراك',
        'system_settings_updated' => 'تحديث إعدادات المنصة',
        'report_exported' => 'تصدير تقرير',
        'refund_requested' => 'تسجيل طلب استرداد',
        'refund_approve' => 'الموافقة على استرداد',
        'refund_reject' => 'رفض استرداد',
        'refund_mark_processed' => 'تسجيل تحويل الاسترداد',
        'provider_review_escalated' => 'تصعيد طلب اعتماد متأخر',
    ];
    $roleLabels = [
        'ops.moderator' => 'مشرف العمليات',
        'finance.reviewer' => 'مراجع مالي',
        'system.admin' => 'مدير النظام',
    ];
    $resourceLabels = [
        'provider' => 'مزود خدمة',
        'review' => 'تقييم',
        'service' => 'خدمة',
        'user' => 'مستخدم',
        'category' => 'تصنيف',
        'subscription_plan' => 'باقة اشتراك',
        'app_settings' => 'إعدادات المنصة',
        'booking_payment' => 'دفعة حجز',
        'admin_refund' => 'استرداد',
        'csv_report' => 'تقرير',
    ];
@endphp

@section('content')
<section class="page-heading">
    <div><h1>سجل التدقيق</h1><p>سجل غير قابل للتعديل لقرارات الإدارة والتغييرات التي تمت على المنصة.</p></div>
    <span class="badge text-bg-light border text-secondary"><i class="fa-solid fa-lock ms-1"></i>سجل محمي</span>
</section>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>التاريخ والوقت</th><th>المنفذ</th><th>الإجراء</th><th>المستخدم المستهدف</th><th>العنصر</th><th>عنوان IP</th><th>التغييرات</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at?->format('Y/m/d H:i:s') }}</td>
                    <td>{{ $log->actor?->name ?? 'النظام' }}<div class="small text-muted">{{ $roleLabels[$log->actor_role] ?? '' }}</div></td>
                    <td><span class="badge text-bg-light border">{{ $actionLabels[$log->action] ?? 'إجراء إداري' }}</span></td>
                    <td>{{ $log->targetUser?->name ?? '—' }}</td>
                    <td>{{ $resourceLabels[$log->resource_type] ?? '—' }}{{ $log->resource_id ? ' #'.$log->resource_id : '' }}</td>
                    <td dir="ltr" class="text-end">{{ $log->ip_address ?? '—' }}</td>
                    <td>
                        <details>
                            <summary class="text-primary fw-bold">عرض التفاصيل</summary>
                            <div class="row mt-2 audit-change-details">
                                <div class="col-lg-6"><strong>قبل التغيير</strong><pre class="bg-light p-2 rounded mt-1">{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                                <div class="col-lg-6"><strong>بعد التغيير</strong><pre class="bg-light p-2 rounded mt-1">{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-5 text-center text-muted"><i class="fa-regular fa-clipboard fs-4 d-block mb-2"></i>لا توجد عمليات مسجلة حتى الآن.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
