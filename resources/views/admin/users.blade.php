@extends('admin.layout')

@section('title', 'المستخدمين')

{{-- فرح: العملاء والمزودين: بحث وحذف حساب. الحذف بيحرر البريد للتسجيل من جديد. --}}
@php($typeLabels = ['customer' => 'عميل', 'provider' => 'مزود خدمة'])
@php($statusLabels = ['active' => 'نشط', 'inactive' => 'غير نشط', 'suspended' => 'موقوف'])

@section('content')
<section class="page-heading">
    <div><h1>المستخدمين</h1><p>العملاء ومزودو الخدمات المسجلون في التطبيق. حذف الحساب يمسح بياناته الشخصية ويحرر بريده للتسجيل من جديد.</p></div>
</section>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label small fw-bold">بحث</label><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="الاسم، البريد الإلكتروني أو رقم الجوال"></div>
        <div class="col-md-3"><label class="form-label small fw-bold">نوع الحساب</label>
            <select name="type" class="form-select">
                <option value="">الكل</option>
                @foreach($typeLabels as $type => $label)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass ms-1"></i>بحث</button><a href="{{ route('admin.users') }}" class="btn btn-outline-primary">مسح</a></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>الاسم</th><th>البريد الإلكتروني</th><th>النوع</th><th>الحالة</th><th>الحجوزات</th><th>تاريخ التسجيل</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                @php($bookings = $user->customer_bookings_count + $user->provider_bookings_count)
                <tr>
                    <td class="fw-bold">
                        {{ $user->name }}
                        @if($user->providerProfile?->business_name)<div class="small text-muted">{{ $user->providerProfile->business_name }}</div>@endif
                    </td>
                    <td dir="ltr" class="text-start">{{ $user->email }}</td>
                    <td>{{ $typeLabels[$user->user_type] ?? $user->user_type }}</td>
                    <td>{{ $statusLabels[$user->status] ?? $user->status }}</td>
                    <td>{{ $bookings }}</td>
                    <td class="small text-muted">{{ $user->created_at?->format('Y-m-d') }}</td>
                    <td class="text-start">
                        <form method="POST" action="{{ route('admin.users.delete', $user) }}" onsubmit="return confirm('حذف حساب {{ $user->email }} نهائيًا؟ لا يمكن التراجع، وسيصبح البريد متاحًا للتسجيل من جديد.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-trash-can ms-1"></i>حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-5 text-center text-muted">لا يوجد مستخدمون مطابقون.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
