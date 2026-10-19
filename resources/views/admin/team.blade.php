@extends('admin.layout')

@section('title', 'فريق الإدارة')

@section('content')
<section class="page-heading">
    <div><h1>فريق الإدارة</h1><p>إنشاء حسابات الفريق وإدارة أدوارهم. لكل حساب دور واحد فقط.</p></div>
</section>
{{-- فرح: إضافة عضو جديد لفريق الإدارة. --}}
<form method="POST" action="{{ route('admin.team.create') }}" class="card mb-4">
    @csrf
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">إضافة عضو جديد</h2>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label small fw-bold">الاسم</label><input name="name" class="form-control" value="{{ old('name') }}" required maxlength="120"></div>
            <div class="col-md-3"><label class="form-label small fw-bold">البريد الإلكتروني</label><input type="email" name="email" dir="ltr" class="form-control text-start" value="{{ old('email') }}" required maxlength="255"></div>
            <div class="col-md-2"><label class="form-label small fw-bold">الدور</label><select name="role" class="form-select" required>
                <option value="ops.moderator" @selected(old('role') === 'ops.moderator')>مشرف العمليات</option>
                <option value="finance.reviewer" @selected(old('role') === 'finance.reviewer')>مراجع مالي</option>
                <option value="system.admin" @selected(old('role') === 'system.admin')>مدير النظام</option>
            </select></div>
            <div class="col-md-2"><label class="form-label small fw-bold">كلمة المرور</label><input type="password" name="password" class="form-control" required minlength="10" autocomplete="new-password"></div>
            <div class="col-md-2"><label class="form-label small fw-bold">تأكيد كلمة المرور</label><input type="password" name="password_confirmation" class="form-control" required minlength="10" autocomplete="new-password"></div>
        </div>
        <div class="small text-muted mt-2">كلمة المرور 10 أحرف على الأقل وتحتوي حروفًا وأرقامًا.</div>
        <button class="btn btn-primary mt-3"><i class="fa-solid fa-user-plus ms-2"></i>إنشاء الحساب</button>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>المسؤول</th><th>الدور الحالي</th><th>حالة الحساب</th><th>تحديث الصلاحية</th></tr></thead>
            <tbody>
            @forelse($admins as $admin)
                @php($roleLabels = ['ops.moderator' => 'مشرف العمليات', 'finance.reviewer' => 'مراجع مالي', 'system.admin' => 'مدير النظام'])
                <tr>
                    <td class="fw-bold">{{ $admin->name }}<div class="small text-muted">{{ $admin->email }}</div></td>
                    <td>{{ $admin->getRoleNames()->map(fn ($role) => $roleLabels[$role] ?? $role)->implode('، ') }}</td>
                    <td><span class="badge {{ $admin->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $admin->status === 'active' ? 'نشط' : 'معطل' }}</span></td>
                    <td>
                        @if($admin->is(auth()->user()))
                            <span class="text-muted small">لا يمكن تعديل صلاحيتك من هنا.</span>
                        @else
                            <form method="POST" action="{{ route('admin.team.update_role', $admin) }}" class="d-flex flex-wrap gap-2">
                                @csrf
                                <select name="role" class="form-select form-select-sm" required>
                                    @foreach(['ops.moderator', 'finance.reviewer', 'system.admin'] as $role)
                                        <option value="{{ $role }}" @selected($admin->hasRole($role))>{{ $roleLabels[$role] }}</option>
                                    @endforeach
                                </select>
                                <select name="status" class="form-select form-select-sm" required>
                                    <option value="active" @selected($admin->status === 'active')>نشط</option>
                                    <option value="inactive" @selected($admin->status !== 'active')>معطل</option>
                                </select>
                                <button class="btn btn-sm btn-primary">حفظ التغييرات</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-5 text-center text-muted">لا توجد حسابات إدارية.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $admins->links() }}</div>
@endsection
