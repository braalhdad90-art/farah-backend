@extends('admin.layout')

@section('title', 'مراجعة الخدمات')

@section('content')
<section class="page-heading">
    <div><h1>مراجعة إعلانات الخدمات</h1><p>إخفاء الإعلان يمنع ظهوره في التصفح والتفاصيل والحجوزات حتى لو عدّل المزود بياناته.</p></div>
    <a class="btn btn-outline-primary" href="{{ route('admin.reviews') }}"><i class="fa-regular fa-star ms-2"></i>مراجعة التقييمات</a>
</section>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-6"><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="ابحث عن الخدمة أو المزود"></div>
        <div class="col-md-4"><select name="visibility" class="form-select"><option value="">كل الإعلانات</option><option value="visible" @selected(($filters['visibility'] ?? '') === 'visible')>غير مخفي إدارياً</option><option value="hidden" @selected(($filters['visibility'] ?? '') === 'hidden')>مخفي إدارياً</option></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">تصفية</button></div>
    </div>
</form>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>الخدمة</th><th>المزود</th><th>التصنيف</th><th>حالة الإعلان</th><th>الإجراء</th></tr></thead>
        <tbody>
        @forelse($services as $service)
            <tr>
                <td class="fw-bold">{{ $service->title }}<div class="small text-muted">حالة الخدمة: {{ $service->status }}</div></td>
                <td>{{ $service->provider?->name ?? 'حساب محذوف' }}</td>
                <td>{{ $service->category?->name ?? '—' }}</td>
                <td><span class="badge {{ $service->admin_hidden ? 'text-bg-danger' : 'text-bg-success' }}">{{ $service->admin_hidden ? 'مخفي إدارياً' : 'غير مخفي' }}</span></td>
                <td>
                    @if($service->status === 'active')
                        <form method="POST" action="{{ route('admin.content.services.visibility', $service) }}" class="d-flex flex-column flex-lg-row gap-2">
                            @csrf
                            @if($service->admin_hidden)
                                <button name="action" value="restore" class="btn btn-sm btn-success">إعادة إظهار</button>
                            @else
                                <input name="reason" class="form-control form-control-sm" placeholder="سبب الإخفاء مطلوب" minlength="5" maxlength="2000" required><button name="action" value="hide" class="btn btn-sm btn-outline-danger">إخفاء الإعلان</button>
                            @endif
                        </form>
                    @else<span class="text-muted small">تتوفر المراجعة للإعلانات النشطة فقط.</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-5 text-center text-muted">لا توجد إعلانات مطابقة.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $services->links() }}</div>
@endsection
