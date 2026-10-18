@extends('admin.layout')

@section('title', 'إعدادات المنصة')

@section('content')
<section class="page-heading">
    <div><h1>إعدادات المنصة</h1><p>بيانات التواصل التي يمكن عرضها داخل التطبيق.</p></div>
</section>
<div class="alert alert-info d-flex gap-2"><i class="fa-solid fa-shield-halved mt-1"></i><span>لا تحفظ كلمات مرور أو مفاتيح API أو بيانات دفع سرية هنا. تُدار الأسرار من متغيرات البيئة الآمنة.</span></div>
<form method="POST" action="{{ route('admin.settings.update') }}" class="card">
    @csrf @method('PUT')
    <div class="card-body p-4">
        <div class="mb-3"><label class="form-label fw-bold">اسم المنصة</label><input name="platform_name" class="form-control" value="{{ old('platform_name', $settings['platform_name'] ?? config('app.name')) }}" required maxlength="120"></div>
        <div class="mb-3"><label class="form-label fw-bold">بريد الدعم</label><input type="email" name="support_email" dir="ltr" class="form-control text-start" value="{{ old('support_email', $settings['support_email'] ?? '') }}" maxlength="255"></div>
        <div class="mb-4"><label class="form-label fw-bold">هاتف الدعم</label><input name="support_phone" dir="ltr" class="form-control text-start" value="{{ old('support_phone', $settings['support_phone'] ?? '') }}" maxlength="40"></div>
        <button class="btn btn-primary"><i class="fa-regular fa-floppy-disk ms-2"></i>حفظ الإعدادات</button>
    </div>
</form>
@endsection
