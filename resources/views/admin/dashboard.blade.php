@extends('admin.layout')

@section('title', 'نظرة عامة')

@php
    $statLabels = [
        'active_users' => 'المستخدمون النشطون',
        'providers_pending' => 'طلبات بانتظار المراجعة',
        'approved_providers' => 'المزودون المعتمدون',
        'providers' => 'إجمالي المزودين',
        'bookings' => 'إجمالي الحجوزات',
        'subscription_proofs_pending' => 'إشعارات اشتراك بانتظار المراجعة',
        'subscriptions_expiring' => 'اشتراكات تنتهي خلال 7 أيام',
    ];
    $bookingStatusLabels = [
        'provider_pending' => 'بانتظار رد المزود',
        'payment_awaiting' => 'بانتظار الدفع',
        'review_under_proof' => 'إثبات دفع قيد مراجعة المزود',
        'confirmed' => 'مؤكدة',
        'completed' => 'مكتملة',
        'rejected' => 'مرفوضة',
        'cancelled' => 'ملغاة',
        'expired' => 'منتهية المهلة',
    ];
    $statIcons = [
        'active_users' => 'fa-user-group',
        'providers_pending' => 'fa-user-clock',
        'approved_providers' => 'fa-user-check',
        'providers' => 'fa-briefcase',
        'bookings' => 'fa-calendar-check',
        'subscription_proofs_pending' => 'fa-receipt',
        'subscriptions_expiring' => 'fa-hourglass-half',
    ];
    $greeting = now()->hour < 12 ? 'صباح الخير' : 'مساء الخير';
@endphp

@section('content')
<section class="page-heading">
    <div>
        <h1>{{ $greeting }}، {{ auth()->user()->name }}</h1>
        <p>إليك ملخص العمل والطلبات التي تحتاج إلى متابعة.</p>
    </div>
    <span class="badge text-bg-light border text-secondary px-3 py-2">
        <i class="fa-solid fa-circle-user ms-1"></i>
        {{ ['ops.moderator' => 'مشرف العمليات', 'finance.reviewer' => 'مراجع مالي', 'system.admin' => 'مدير النظام'][$role] ?? 'مدير' }}
    </span>
</section>

<div class="row g-3 mb-4">
    @foreach($stats as $key => $value)
        <div class="col-6 col-xl-3">
            <article class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small mb-2">{{ $statLabels[$key] ?? $key }}</div>
                        <div class="fs-3 fw-bold">{{ number_format($value) }}</div>
                    </div>
                    <span class="identity-avatar"><i class="fa-solid {{ $statIcons[$key] ?? 'fa-chart-simple' }}"></i></span>
                </div>
            </article>
        </div>
    @endforeach
</div>

<div class="row g-3">
    @if(in_array($role, ['ops.moderator', 'system.admin'], true))
        <div class="col-lg-6">
            <article class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="text-muted small">العمليات</span><h5 class="fw-bold mt-1 mb-2">طلبات اعتماد المزودين</h5></div>
                        <span class="identity-avatar"><i class="fa-solid fa-user-check"></i></span>
                    </div>
                    <p class="text-muted mb-3">لديك <strong class="text-dark">{{ number_format($providerPending) }}</strong> طلبات تنتظر المراجعة.</p>
                    <a href="{{ route('admin.providers.pending') }}" class="btn btn-primary"><i class="fa-solid fa-arrow-left ms-2"></i>عرض الطلبات</a>
                </div>
            </article>
        </div>
    @endif
    @if(in_array($role, ['finance.reviewer', 'system.admin'], true))
        <div class="col-lg-6">
            <article class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="text-muted small">المالية</span><h5 class="fw-bold mt-1 mb-2">إشعارات تحويل الاشتراكات</h5></div>
                        <span class="identity-avatar"><i class="fa-solid fa-receipt"></i></span>
                    </div>
                    <p class="text-muted mb-3">عدد الإشعارات المعلقة: <strong class="text-dark">{{ number_format($stats['subscription_proofs_pending'] ?? 0) }}</strong>.</p>
                    <a href="{{ route('admin.finance.subscriptions') }}" class="btn btn-primary"><i class="fa-solid fa-arrow-left ms-2"></i>مراجعة الإشعارات</a>
                </div>
            </article>
        </div>
    @endif
    @if($bookingsByStatus->isNotEmpty())
        <div class="col-lg-6">
            <article class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div><span class="text-muted small">الحجوزات</span><h5 class="fw-bold mt-1 mb-0">الحجوزات حسب الحالة</h5></div>
                        <span class="identity-avatar"><i class="fa-solid fa-calendar-check"></i></span>
                    </div>
                    <ul class="list-unstyled mb-0">
                        @foreach($bookingsByStatus as $status => $total)
                            <li class="d-flex justify-content-between border-bottom py-2"><span>{{ $bookingStatusLabels[$status] ?? $status }}</span><strong>{{ number_format($total) }}</strong></li>
                        @endforeach
                    </ul>
                </div>
            </article>
        </div>
    @endif
    @if($role === 'system.admin')
        <div class="col-lg-6">
            <article class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="text-muted small">الرقابة</span><h5 class="fw-bold mt-1 mb-2">سجل العمليات الإدارية</h5></div>
                        <span class="identity-avatar"><i class="fa-solid fa-clipboard-list"></i></span>
                    </div>
                    <p class="text-muted mb-3">تابع تغييرات الفريق وسجل القرارات والتعديلات.</p>
                    <a href="{{ route('admin.audit-log') }}" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left ms-2"></i>فتح سجل التدقيق</a>
                </div>
            </article>
        </div>
    @endif
</div>
@endsection
