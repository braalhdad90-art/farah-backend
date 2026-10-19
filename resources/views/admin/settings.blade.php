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
        {{-- فرح: حساب المنصة الذي يحوّل عليه المزودون قيمة الاشتراك، ويظهر في شاشة تفعيل الاشتراك بالتطبيق. --}}
        <h2 class="h6 fw-bold mb-1">حساب التحويل لاشتراكات المزودين</h2>
        <p class="small text-muted mb-3">تظهر هذه البيانات للمزود في شاشة تفعيل الاشتراك. اتركها فارغة لإخفاء الدفع البنكي.</p>
        <div class="mb-3"><label class="form-label fw-bold">مكان التحويل (اسم البنك أو المحفظة)</label><input name="bank_name" class="form-control" value="{{ old('bank_name', $settings['bank_name'] ?? '') }}" maxlength="120"></div>
        <div class="mb-3"><label class="form-label fw-bold">رقم الحساب</label><input name="bank_account_number" dir="ltr" class="form-control text-start" value="{{ old('bank_account_number', $settings['bank_account_number'] ?? '') }}" maxlength="60"></div>
        <div class="mb-4"><label class="form-label fw-bold">الآيبان</label><input name="bank_iban" dir="ltr" class="form-control text-start" value="{{ old('bank_iban', $settings['bank_iban'] ?? '') }}" maxlength="60"></div>
        {{-- فرح: مهل الخدمة وساعات العمل (ADM-48). --}}
        <h2 class="h6 fw-bold mb-1">مهل الخدمة وساعات العمل</h2>
        <p class="small text-muted mb-3">تُطبَّق المهل الجديدة على الطلبات من لحظة الحفظ.</p>
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label fw-bold">مهلة مراجعة طلب المزود (بالساعات)</label><input type="number" min="1" max="168" name="provider_review_sla_hours" class="form-control" value="{{ old('provider_review_sla_hours', $settings['provider_review_sla_hours'] ?? 24) }}" required></div>
            <div class="col-md-6"><label class="form-label fw-bold">مهلة دفع العربون بعد قبول الحجز (بالساعات)</label><input type="number" min="1" max="168" name="payment_due_hours" class="form-control" value="{{ old('payment_due_hours', $settings['payment_due_hours'] ?? 24) }}" required></div>
        </div>
        <div class="mb-4"><label class="form-label fw-bold">ساعات عمل الدعم</label><input name="working_hours" class="form-control" value="{{ old('working_hours', $settings['working_hours'] ?? '') }}" maxlength="255" placeholder="مثال: من السبت إلى الخميس، 9 صباحًا - 5 مساءً"></div>
        <button class="btn btn-primary"><i class="fa-regular fa-floppy-disk ms-2"></i>حفظ الإعدادات</button>
    </div>
</form>
@endsection
