@extends('admin.layout')

@section('title', 'طلبات المزودين')

@section('content')
<section class="page-heading">
    <div><h1>طلبات اعتماد المزودين</h1><p>راجع بيانات النشاط والمستندات قبل اتخاذ قرار الاعتماد.</p></div>
    <a href="{{ route('admin.providers') }}" class="btn btn-outline-primary"><i class="fa-solid fa-users ms-2"></i>كل المزودين</a>
</section>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-md-7"><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="ابحث باسم النشاط أو المزود أو البريد"></div>
        <div class="col-md-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="overdue" value="1" @checked($filters['overdue'] ?? false)> تجاوزت مهلة ٢٤ ساعة</label></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill">تصفية</button><a href="{{ route('admin.providers.pending') }}" class="btn btn-outline-primary">مسح</a></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>النشاط التجاري</th><th>صاحب الحساب</th><th>تاريخ الإرسال</th><th>مهلة المراجعة</th><th></th></tr></thead>
            <tbody>
            @forelse($providers as $provider)
                <tr>
                    <td class="fw-bold">{{ $provider->business_name }}</td>
                    <td>{{ $provider->user?->name }}</td>
                    <td>{{ $provider->submitted_at?->format('Y/m/d H:i') ?? $provider->created_at?->format('Y/m/d H:i') }}</td>
                    <td>
                        @if($provider->submitted_at && $provider->submitted_at->lte(now()->subHours(24)))
                            <span class="badge text-bg-danger"><i class="fa-solid fa-triangle-exclamation ms-1"></i>متأخرة</span>
                        @else
                            <span class="badge text-bg-success">ضمن المهلة</span>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.provider.detail', $provider) }}" class="btn btn-sm btn-primary"><i class="fa-solid fa-magnifying-glass ms-1"></i>مراجعة الطلب</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-5 text-center text-muted"><i class="fa-solid fa-circle-check fs-4 d-block mb-2"></i>لا توجد طلبات معلقة حالياً.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $providers->links() }}</div>
@endsection
