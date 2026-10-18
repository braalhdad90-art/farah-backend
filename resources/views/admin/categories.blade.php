@extends('admin.layout')

@section('title', 'التصنيفات')

@section('content')
<section class="page-heading">
    <div><h1>تصنيفات الخدمات</h1><p>التصنيفات النشطة فقط تظهر للعملاء في التطبيق.</p></div>
</section>
<section class="card mb-3">
    <div class="card-header bg-white py-3"><h2 class="h6 fw-bold mb-0">إضافة تصنيف</h2></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.categories.create') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4"><label class="form-label small fw-bold">اسم التصنيف</label><input name="name" class="form-control" placeholder="مثال: قاعات الأفراح" value="{{ old('name') }}" required maxlength="120"></div>
            <div class="col-md-4"><label class="form-label small fw-bold">الرابط المختصر بالإنجليزية</label><input name="slug" dir="ltr" class="form-control text-start" placeholder="wedding-halls" value="{{ old('slug') }}" required maxlength="140"></div>
            <div class="col-md-2"><label class="form-label small fw-bold">الحالة</label><select name="status" class="form-select"><option value="active">نشط</option><option value="inactive">غير نشط</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fa-solid fa-plus ms-1"></i>إضافة</button></div>
        </form>
    </div>
</section>
<section class="card">
    <div class="card-header bg-white py-3"><h2 class="h6 fw-bold mb-0">التصنيفات الحالية</h2></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>اسم التصنيف</th><th>الرابط المختصر</th><th>الخدمات</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @forelse($categories as $category)
            <tr><td colspan="5">
                <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="row g-2 align-items-center">
                    @csrf @method('PUT')
                    <div class="col-md-3"><input name="name" class="form-control" value="{{ $category->name }}" required maxlength="120" aria-label="اسم التصنيف"></div>
                    <div class="col-md-3"><input name="slug" dir="ltr" class="form-control text-start" value="{{ $category->slug }}" required maxlength="140" aria-label="الرابط المختصر"></div>
                    <div class="col-md-2"><span class="text-muted small"><i class="fa-solid fa-briefcase ms-1"></i>{{ $category->services_count }} خدمة</span></div>
                    <div class="col-md-2"><select name="status" class="form-select"><option value="active" @selected($category->status === 'active')>نشط</option><option value="inactive" @selected($category->status === 'inactive')>غير نشط</option></select></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-outline-primary"><i class="fa-regular fa-floppy-disk ms-1"></i>حفظ</button></div>
                </form>
            </td></tr>
        @empty
            <tr><td colspan="5" class="p-5 text-center text-muted">لا توجد تصنيفات.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
<div class="mt-3">{{ $categories->links() }}</div>
@endsection
