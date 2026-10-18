@extends('admin.layout')

@section('title', 'فريق الإدارة')

@section('content')
<section class="page-heading">
    <div><h1>فريق الإدارة</h1><p>إدارة صلاحيات وحالة حسابات الإدارة الموجودة.</p></div>
    <span class="badge text-bg-light border text-secondary"><i class="fa-solid fa-circle-info ms-1"></i>إنشاء الحسابات يتم بأمر Artisan</span>
</section>
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
