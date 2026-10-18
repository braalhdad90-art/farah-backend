@extends('admin.layout')

@section('title', 'ملف المزود')

@section('content')
<section class="page-heading">
    <div><h1>{{ $provider->business_name }}</h1><p>مقدم الطلب: {{ $provider->user?->name ?? '—' }} · {{ $provider->user?->email ?? '—' }}</p></div>
    <a href="{{ route('admin.providers.pending') }}" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-right ms-2"></i>العودة للطلبات</a>
</section>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <section class="card h-100">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3"><i class="fa-regular fa-id-card ms-2 text-secondary"></i>بيانات المزود</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-3 text-muted py-2">البريد الإلكتروني</dt><dd class="col-sm-9 py-2">{{ $provider->user?->email ?? '—' }}</dd>
                    <dt class="col-sm-3 text-muted py-2">رقم الهاتف</dt><dd class="col-sm-9 py-2">{{ $provider->phone ?? '—' }}</dd>
                    <dt class="col-sm-3 text-muted py-2">التصنيف</dt><dd class="col-sm-9 py-2">{{ $provider->category?->name ?? '—' }}</dd>
                    <dt class="col-sm-3 text-muted py-2">المدينة</dt><dd class="col-sm-9 py-2">{{ $provider->city?->name ?? '—' }}</dd>
                    <dt class="col-sm-3 text-muted py-2">الحالة</dt><dd class="col-sm-9 py-2">
                        @php($statusLabels = ['pending' => 'قيد المراجعة', 'approved' => 'معتمد', 'rejected' => 'مرفوض', 'suspended' => 'موقوف'])
                        <span class="badge text-bg-light border">{{ $statusLabels[$provider->status] ?? $provider->status }}</span>
                    </dd>
                    @if($provider->decision_reason)<dt class="col-sm-3 text-muted py-2">ملاحظة القرار</dt><dd class="col-sm-9 py-2">{{ $provider->decision_reason }}</dd>@endif
                </dl>
            </div>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="card h-100">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3"><i class="fa-regular fa-file-lines ms-2 text-secondary"></i>نبذة النشاط</h2>
                <p class="text-secondary mb-0">{{ $provider->description ?: 'لم يضف المزود وصفاً للنشاط.' }}</p>
            </div>
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['title' => 'وثيقة الهوية', 'path' => $provider->identity_document_path, 'key' => 'identity_document'],
        ['title' => 'السجل التجاري', 'path' => $provider->commercial_register_path, 'key' => 'commercial_register'],
    ] as $document)
        <div class="col-md-6">
            <section class="card h-100"><div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                <div><h2 class="h6 fw-bold mb-1">{{ $document['title'] }}</h2><div class="small text-muted">{{ $document['path'] ? 'مرفق محفوظ بوصول خاص' : 'لم يتم إرفاق مستند' }}</div></div>
                @if($document['path'])
                    <a href="{{ Illuminate\Support\Facades\URL::temporarySignedRoute('admin.provider.document', now()->addMinutes(5), ['provider' => $provider->id, 'document' => $document['key']]) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>فتح آمن · ٥ دقائق</a>
                @else
                    <span class="text-muted"><i class="fa-regular fa-file"></i></span>
                @endif
            </div></section>
        </div>
    @endforeach
</div>

@if(auth()->user()->hasAnyRole(['ops.moderator', 'system.admin']))
    <section class="card">
        <div class="card-body p-4">
            <h2 class="h6 fw-bold mb-3">إجراءات المراجعة</h2>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                @if($provider->status === 'pending')
                    <form method="POST" action="{{ route('admin.provider.approve', $provider) }}">@csrf<button class="btn btn-success"><i class="fa-solid fa-check ms-2"></i>اعتماد المزود</button></form>
                    <form method="POST" action="{{ route('admin.provider.reject', $provider) }}" class="d-flex gap-2">@csrf<input type="text" name="reason" class="form-control" placeholder="سبب الرفض" required minlength="5" maxlength="2000"><button class="btn btn-outline-danger"><i class="fa-solid fa-xmark ms-1"></i>رفض الطلب</button></form>
                @elseif($provider->status === 'approved')
                    <form method="POST" action="{{ route('admin.provider.suspend', $provider) }}" class="d-flex gap-2">@csrf<input name="reason" class="form-control" placeholder="سبب الإيقاف" required minlength="5" maxlength="2000"><button class="btn btn-warning"><i class="fa-solid fa-ban ms-1"></i>إيقاف المزود</button></form>
                @elseif($provider->status === 'suspended')
                    <form method="POST" action="{{ route('admin.provider.reactivate', $provider) }}" class="d-flex gap-2">@csrf<input name="reason" class="form-control" placeholder="سبب إعادة التفعيل" required minlength="5" maxlength="2000"><button class="btn btn-outline-success"><i class="fa-solid fa-rotate-right ms-1"></i>إعادة التفعيل</button></form>
                @else
                    <span class="text-muted small">لا توجد إجراءات متاحة لهذه الحالة.</span>
                @endif
            </div>
        </div>
    </section>
@endif
@endsection
