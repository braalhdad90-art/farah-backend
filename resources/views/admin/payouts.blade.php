@extends('admin.layout')

@section('title', 'تحويلات المزودين')

@section('content')
{{-- فرح: تسجيل ما حوّلته المنصة لحساب المزود (ADM-26/31). التحويل نفسه يتم خارج النظام. --}}
@php($methods = ['bank_transfer' => 'تحويل بنكي', 'wallet' => 'محفظة إلكترونية', 'cash' => 'نقدًا'])
<section class="page-heading">
    <div><h1>تحويلات المزودين</h1><p>سجّل كل تحويل أرسلته المنصة لمزود مع رقم العملية، ويصل المزود إشعار بذلك.</p></div>
</section>

<form method="POST" action="{{ route('admin.finance.payouts.create') }}" class="card mb-4">
    @csrf
    <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">تسجيل تحويل جديد</h2>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label small fw-bold">المزود</label><select name="provider_id" class="form-select" required>
                <option value="">اختر المزود</option>
                @foreach($providers as $provider)
                    <option value="{{ $provider->id }}" @selected((int) old('provider_id') === $provider->id)>{{ $provider->providerProfile?->business_name ?? $provider->name }} — {{ $provider->name }}</option>
                @endforeach
            </select></div>
            <div class="col-md-2"><label class="form-label small fw-bold">المبلغ</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required></div>
            <div class="col-md-1"><label class="form-label small fw-bold">العملة</label><input name="currency" dir="ltr" class="form-control text-start" value="{{ old('currency', 'ILS') }}" required maxlength="3"></div>
            <div class="col-md-2"><label class="form-label small fw-bold">طريقة التحويل</label><select name="method" class="form-select" required>
                @foreach($methods as $value => $label)
                    <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
            <div class="col-md-3"><label class="form-label small fw-bold">تاريخ التحويل</label><input type="date" name="paid_at" class="form-control" value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
            <div class="col-md-4"><label class="form-label small fw-bold">رقم العملية / المرجع</label><input name="reference" dir="ltr" class="form-control text-start" value="{{ old('reference') }}" required maxlength="120"></div>
            <div class="col-md-3"><label class="form-label small fw-bold">رقم الحجز (اختياري)</label><input name="booking_reference" dir="ltr" class="form-control text-start" value="{{ old('booking_reference') }}" maxlength="40"></div>
            <div class="col-md-5"><label class="form-label small fw-bold">ملاحظة</label><input name="note" class="form-control" value="{{ old('note') }}" maxlength="2000"></div>
        </div>
        <button class="btn btn-primary mt-3"><i class="fa-solid fa-money-bill-transfer ms-2"></i>تسجيل التحويل</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>التاريخ</th><th>المزود</th><th>المبلغ</th><th>الطريقة</th><th>المرجع</th><th>الحجز</th><th>سجّله</th></tr></thead>
            <tbody>
            @forelse($payouts as $payout)
                <tr>
                    <td>{{ $payout->paid_at?->format('Y-m-d') }}</td>
                    <td class="fw-bold">{{ $payout->provider?->providerProfile?->business_name ?? $payout->provider?->name ?? '—' }}</td>
                    <td class="fw-bold">{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</td>
                    <td>{{ $methods[$payout->method] ?? $payout->method }}</td>
                    <td dir="ltr" class="text-end">{{ $payout->reference }}</td>
                    <td>{{ $payout->booking?->reference ?? '—' }}</td>
                    <td>{{ $payout->recorder?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-5 text-center text-muted"><i class="fa-solid fa-money-bill-transfer fs-4 d-block mb-2"></i>لم يُسجَّل أي تحويل بعد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $payouts->links() }}</div>
@endsection
