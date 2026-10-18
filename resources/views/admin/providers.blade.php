@extends('admin.layout')

@section('title', 'إدارة المزودين')

@section('content')
<section class="page-heading">
    <div><h1>إدارة مزودي الخدمات</h1><p>ابحث عن المزودين وتابع حالة حساباتهم.</p></div>
    <a href="{{ route('admin.providers.pending') }}" class="btn btn-outline-primary"><i class="fa-solid fa-user-clock ms-2"></i>طلبات بانتظار المراجعة</a>
</section>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small fw-bold">بحث</label><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="اسم النشاط، المزود أو البريد الإلكتروني"></div>
        <div class="col-md-3"><label class="form-label small fw-bold">الحالة</label>
            <select name="status" class="form-select">
                <option value="">كل الحالات</option>
                @foreach(['pending' => 'قيد المراجعة', 'approved' => 'معتمد', 'rejected' => 'مرفوض', 'suspended' => 'موقوف'] as $status => $label)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small fw-bold">التصنيف</label>
            <select name="category_id" class="form-select"><option value="">كل التصنيفات</option>
                @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass ms-1"></i>بحث</button><a href="{{ route('admin.providers') }}" class="btn btn-outline-primary">مسح</a></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>النشاط التجاري</th><th>اسم المزود</th><th>البريد الإلكتروني</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @forelse($providers as $provider)
                @php($statusLabels = ['pending' => 'قيد المراجعة', 'approved' => 'معتمد', 'rejected' => 'مرفوض', 'suspended' => 'موقوف'])
                <tr>
                    <td class="fw-bold">{{ $provider->business_name ?? '—' }}</td>
                    <td>{{ $provider->user?->name ?? '—' }}</td>
                    <td>{{ $provider->user?->email ?? '—' }}</td>
                    <td><span class="badge {{ $provider->status === 'approved' ? 'text-bg-success' : ($provider->status === 'rejected' || $provider->status === 'suspended' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $statusLabels[$provider->status] ?? $provider->status }}</span></td>
                    <td><a href="{{ route('admin.provider.detail', $provider) }}" class="btn btn-sm btn-outline-primary">عرض الملف <i class="fa-solid fa-arrow-left me-1"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-5 text-center text-muted"><i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i>لا توجد نتائج مطابقة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $providers->links() }}</div>
@endsection
