@extends('admin.layout')

@section('title', 'مراجعة المحتوى')

@section('content')
<section class="page-heading">
    <div><h1>مراجعة تقييمات العملاء</h1><p>يمكن إخفاء التقييم المخالف أو إعادته للظهور بعد المراجعة.</p></div>
    <a class="btn btn-outline-primary" href="{{ route('admin.content.services') }}"><i class="fa-solid fa-briefcase ms-2"></i>مراجعة الخدمات</a>
</section>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-6"><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="ابحث في التقييم أو اسم العميل أو الخدمة"></div>
        <div class="col-md-4"><select name="visibility" class="form-select"><option value="">كل التقييمات</option><option value="visible" @selected(($filters['visibility'] ?? '') === 'visible')>ظاهرة</option><option value="hidden" @selected(($filters['visibility'] ?? '') === 'hidden')>مخفية</option></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter ms-1"></i>تصفية</button></div>
    </div>
</form>

@forelse($reviews as $review)
    <article class="card mb-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between gap-3">
                <div>
                    <div class="fw-bold">{{ $review->service?->title ?? 'خدمة محذوفة' }}</div>
                    <div class="small text-muted mt-1">{{ $review->user?->name ?? 'حساب محذوف' }} · {{ $review->created_at?->format('Y/m/d H:i') }}</div>
                </div>
                <div class="text-nowrap"><span class="text-warning"><i class="fa-solid fa-star"></i></span> <strong>{{ $review->rating }}/5</strong> <span class="badge {{ $review->is_visible ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $review->is_visible ? 'ظاهر' : 'مخفي' }}</span></div>
            </div>
            <p class="my-3">{{ $review->comment ?: 'لم يكتب العميل تعليقاً.' }}</p>
            @if(!$review->is_visible && $review->moderation_reason)<div class="alert alert-light small mb-3">سبب الإخفاء: {{ $review->moderation_reason }}</div>@endif
            <form method="POST" action="{{ route('admin.reviews.action', $review) }}" class="d-flex flex-column flex-md-row gap-2">
                @csrf
                @if($review->is_visible)
                    <input name="reason" class="form-control" placeholder="سبب الإخفاء مطلوب" minlength="5" maxlength="2000" required>
                    <button name="action" value="hide" class="btn btn-outline-danger"><i class="fa-solid fa-eye-slash ms-1"></i>إخفاء التقييم</button>
                @else
                    <button name="action" value="restore" class="btn btn-success"><i class="fa-solid fa-eye ms-1"></i>إعادة إظهار التقييم</button>
                @endif
            </form>
        </div>
    </article>
@empty
    <div class="card p-5 text-center text-muted"><i class="fa-regular fa-comment-dots fs-4 d-block mb-2"></i>لا توجد تقييمات مطابقة.</div>
@endforelse
<div class="mt-3">{{ $reviews->links() }}</div>
@endsection
