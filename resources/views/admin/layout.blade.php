@php
    $user = auth()->user();
    $role = $user->getRoleNames()->first() ?? 'system.admin';
    $roleLabels = [
        'ops.moderator' => 'مشرف العمليات',
        'finance.reviewer' => 'مراجع مالي',
        'system.admin' => 'مدير النظام',
    ];
    $navItems = [
        ['label' => 'نظرة عامة', 'icon' => 'fa-chart-pie', 'route' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')],
    ];

    if (in_array($role, ['ops.moderator', 'system.admin'], true)) {
        $navItems[] = ['label' => 'طلبات المزودين', 'icon' => 'fa-user-check', 'route' => route('admin.providers.pending'), 'active' => request()->routeIs('admin.providers.pending', 'admin.provider.*')];
        $navItems[] = ['label' => 'إدارة المزودين', 'icon' => 'fa-users', 'route' => route('admin.providers'), 'active' => request()->routeIs('admin.providers')];
        $navItems[] = ['label' => 'مراجعة المحتوى', 'icon' => 'fa-shield-halved', 'route' => route('admin.reviews'), 'active' => request()->routeIs('admin.reviews*', 'admin.content.services*')];
        $navItems[] = ['label' => 'تقرير الحجوزات', 'icon' => 'fa-file-csv', 'route' => route('admin.export.bookings'), 'active' => false];
    }

    if (in_array($role, ['finance.reviewer', 'system.admin'], true)) {
        $navItems[] = ['label' => 'دفعات الحجوزات', 'icon' => 'fa-money-check-dollar', 'route' => route('admin.finance.payments'), 'active' => request()->routeIs('admin.finance.payments')];
        $navItems[] = ['label' => 'اشتراكات المزودين', 'icon' => 'fa-receipt', 'route' => route('admin.finance.subscriptions'), 'active' => request()->routeIs('admin.finance.subscription*')];
        $navItems[] = ['label' => 'الاستردادات', 'icon' => 'fa-arrow-rotate-left', 'route' => route('admin.finance.refunds'), 'active' => request()->routeIs('admin.finance.refunds*')];
        $navItems[] = ['label' => 'تحويلات المزودين', 'icon' => 'fa-money-bill-transfer', 'route' => route('admin.finance.payouts'), 'active' => request()->routeIs('admin.finance.payouts*')];
        $navItems[] = ['label' => 'تقرير الاشتراكات', 'icon' => 'fa-file-csv', 'route' => route('admin.finance.export.subscriptions'), 'active' => false];
        $navItems[] = ['label' => 'تقرير الدفعات', 'icon' => 'fa-file-invoice-dollar', 'route' => route('admin.finance.export.payments'), 'active' => false];
    }

    if ($role === 'system.admin') {
        $navItems[] = ['label' => 'سجل التدقيق', 'icon' => 'fa-clipboard-list', 'route' => route('admin.audit-log'), 'active' => request()->routeIs('admin.audit-log')];
        $navItems[] = ['label' => 'فريق الإدارة', 'icon' => 'fa-user-gear', 'route' => route('admin.team'), 'active' => request()->routeIs('admin.team*')];
        $navItems[] = ['label' => 'التصنيفات', 'icon' => 'fa-layer-group', 'route' => route('admin.categories'), 'active' => request()->routeIs('admin.categories*')];
        $navItems[] = ['label' => 'باقات الاشتراك', 'icon' => 'fa-tags', 'route' => route('admin.plans'), 'active' => request()->routeIs('admin.plans*')];
        $navItems[] = ['label' => 'إعدادات المنصة', 'icon' => 'fa-sliders', 'route' => route('admin.settings'), 'active' => request()->routeIs('admin.settings*')];
    }
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة فرح') — فرح</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
<div class="container-fluid admin-shell">
    <div class="row min-vh-100">
        <aside class="col-md-3 col-xl-2 sidebar d-flex flex-column">
            <a href="{{ route('admin.dashboard') }}" class="brand d-flex align-items-center gap-3">
                <span class="brand-mark"><i class="fa-solid fa-feather-pointed"></i></span>
                <span><span class="brand-title d-block">فرح</span><span class="brand-caption d-block">إدارة المنصة</span></span>
            </a>
            <div class="nav-section">
                <div class="nav-caption">القائمة الرئيسية</div>
                <nav class="admin-nav">
                    @foreach($navItems as $item)
                        <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['route'] }}">
                            <i class="fa-solid {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
            <div class="sidebar-bottom">
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="nav-link logout-link border-0 bg-transparent w-100 text-start">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i><span>تسجيل الخروج</span>
                    </button>
                </form>
            </div>
        </aside>
        <main class="col-md-9 col-xl-10 main-column p-0">
            <header class="topbar">
                <div><div class="topbar-context">لوحة التحكم <i class="fa-solid fa-chevron-left mx-1 small"></i> @yield('title', 'نظرة عامة')</div><div class="topbar-title">@yield('title', 'نظرة عامة')</div></div>
                <div class="d-flex align-items-center gap-3">
                    <span class="session-pill"><i class="fa-regular fa-clock ms-1"></i> تنتهي الجلسة بعد ٣٠ دقيقة من الخمول</span>
                    <div class="admin-identity">
                        <div class="text-end"><div class="identity-name">{{ $user->name }}</div><div class="identity-role">{{ $roleLabels[$role] ?? 'مدير' }}</div></div>
                        <span class="identity-avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                    </div>
                </div>
            </header>
            <div class="page-content">
                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center gap-2"><i class="fa-solid fa-circle-check"></i><span>{{ session('success') }}</span></div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation ms-1"></i>تعذر تنفيذ الطلب</div>
                        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </div>
            <footer class="px-4 py-3 text-muted small border-top bg-white d-flex justify-content-between">
                <span>فرح — لوحة الإدارة</span><span>{{ now()->format('Y/m/d') }}</span>
            </footer>
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
