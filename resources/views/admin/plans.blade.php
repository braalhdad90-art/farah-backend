@extends('admin.layout')

@section('title', 'باقات الاشتراك')

@section('content')
<section class="page-heading">
    <div><h1>باقات الاشتراك</h1><p>تعديل السعر أو حالة الباقة يؤثر على الاشتراكات الجديدة، ولا يغيّر مدة اشتراك قائم.</p></div>
</section>
@forelse($plans as $plan)
    <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="card mb-3">
        @csrf @method('PUT')
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div><h2 class="h6 fw-bold mb-1">{{ $plan->name }}</h2><div class="small text-muted">المعرّف: <span dir="ltr">{{ $plan->slug }}</span> · {{ $plan->provider_subscriptions_count }} اشتراك</div></div>
                <span class="badge {{ $plan->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $plan->is_active ? 'نشطة' : 'متوقفة' }}</span>
            </div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label small fw-bold">اسم الباقة</label><input name="name" class="form-control" value="{{ $plan->name }}" required maxlength="120"></div>
                <div class="col-md-4"><label class="form-label small fw-bold">الوصف</label><input name="description" class="form-control" value="{{ $plan->description }}" maxlength="2000"></div>
                <div class="col-md-2"><label class="form-label small fw-bold">السعر</label><input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ $plan->price }}" required></div>
                <div class="col-md-1"><label class="form-label small fw-bold">العملة</label><input name="currency" dir="ltr" class="form-control text-start" value="{{ $plan->currency }}" required maxlength="3"></div>
                <div class="col-md-1"><label class="form-label small fw-bold">المدة</label><select name="interval" class="form-select"><option value="">—</option><option value="month" @selected($plan->interval === 'month')>شهر</option><option value="year" @selected($plan->interval === 'year')>سنة</option></select></div>
                <div class="col-md-1"><label class="form-label small fw-bold">العدد</label><input type="number" min="1" max="24" name="interval_count" class="form-control" value="{{ $plan->interval_count }}" required></div>
                <div class="col-md-3"><label class="form-label small fw-bold">حالة الباقة</label><select name="is_active" class="form-select"><option value="1" @selected($plan->is_active)>نشطة</option><option value="0" @selected(!$plan->is_active)>متوقفة</option></select></div>
                @php($features = collect($plan->features ?? []))
                <div class="col-md-6"><label class="form-label small fw-bold">المميزات المشمولة (ميزة في كل سطر)</label><textarea name="included_features" class="form-control" rows="4" maxlength="2000">{{ $features->where('included', true)->pluck('name')->implode("\n") }}</textarea></div>
                <div class="col-md-6"><label class="form-label small fw-bold">مميزات غير مشمولة (تظهر باهتة)</label><textarea name="excluded_features" class="form-control" rows="4" maxlength="2000">{{ $features->where('included', false)->pluck('name')->implode("\n") }}</textarea></div>
                <div class="col-md-3 align-self-end"><button class="btn btn-primary"><i class="fa-regular fa-floppy-disk ms-2"></i>حفظ الباقة</button></div>
            </div>
        </div>
    </form>
@empty
    <div class="card p-5 text-center text-muted">لا توجد باقات اشتراك.</div>
@endforelse
@endsection
