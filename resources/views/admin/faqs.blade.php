@extends('admin.layout')

@section('title', 'الأسئلة الشائعة')

{{-- فرح: الأسئلة التي تظهر في "مركز المساعدة" داخل التطبيق. --}}
@php($audiences = ['provider' => 'المزودون', 'customer' => 'العملاء', 'all' => 'الجميع'])

@section('content')
<section class="page-heading">
    <div><h1>الأسئلة الشائعة</h1><p>تظهر في شاشة "مركز المساعدة" بالتطبيق. الأسئلة غير الظاهرة لا تصل للمستخدمين.</p></div>
</section>
<section class="card mb-3">
    <div class="card-header bg-white py-3"><h2 class="h6 fw-bold mb-0">إضافة سؤال</h2></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.faqs.create') }}" class="row g-2">
            @csrf
            <div class="col-md-3"><label class="form-label small fw-bold">يظهر لـ</label><select name="audience" class="form-select">@foreach($audiences as $value => $label)<option value="{{ $value }}" @selected(old('audience') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-7"><label class="form-label small fw-bold">السؤال</label><input name="question" class="form-control" value="{{ old('question') }}" required maxlength="255"></div>
            <div class="col-md-2"><label class="form-label small fw-bold">الترتيب</label><input type="number" name="sort_order" min="0" max="1000" class="form-control" value="{{ old('sort_order', 0) }}"></div>
            <div class="col-12"><label class="form-label small fw-bold">الإجابة</label><textarea name="answer" rows="3" class="form-control" required maxlength="5000">{{ old('answer') }}</textarea></div>
            <input type="hidden" name="is_active" value="1">
            <div class="col-12"><button class="btn btn-primary"><i class="fa-solid fa-plus ms-1"></i>إضافة</button></div>
        </form>
    </div>
</section>
<section class="card">
    <div class="card-header bg-white py-3"><h2 class="h6 fw-bold mb-0">الأسئلة الحالية</h2></div>
    <div class="card-body">
        @forelse($faqs as $faq)
            <div class="border rounded p-3 mb-3">
                <form method="POST" action="{{ route('admin.faqs.update', $faq) }}" class="row g-2">
                    @csrf @method('PUT')
                    <div class="col-md-3"><select name="audience" class="form-select" aria-label="يظهر لـ">@foreach($audiences as $value => $label)<option value="{{ $value }}" @selected($faq->audience === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-7"><input name="question" class="form-control" value="{{ $faq->question }}" required maxlength="255" aria-label="السؤال"></div>
                    <div class="col-md-2"><input type="number" name="sort_order" min="0" max="1000" class="form-control" value="{{ $faq->sort_order }}" aria-label="الترتيب"></div>
                    <div class="col-12"><textarea name="answer" rows="2" class="form-control" required maxlength="5000" aria-label="الإجابة">{{ $faq->answer }}</textarea></div>
                    <div class="col-md-6 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="faq-active-{{ $faq->id }}" @checked($faq->is_active)><label class="form-check-label" for="faq-active-{{ $faq->id }}">ظاهر في التطبيق</label></div></div>
                    <div class="col-md-6 text-start"><button class="btn btn-sm btn-outline-primary"><i class="fa-regular fa-floppy-disk ms-1"></i>حفظ</button></div>
                </form>
                <form method="POST" action="{{ route('admin.faqs.delete', $faq) }}" class="mt-2 text-start" onsubmit="return confirm('حذف هذا السؤال؟')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-trash-can ms-1"></i>حذف</button>
                </form>
            </div>
        @empty
            <p class="text-center text-muted p-4 mb-0">لا توجد أسئلة بعد.</p>
        @endforelse
    </div>
</section>
@endsection
