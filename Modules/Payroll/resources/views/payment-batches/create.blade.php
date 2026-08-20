@extends('layouts.admin')

@section('title', 'Create Payment Batch')
@section('header_action')<a class="button secondary" href="{{ route('admin.payroll.payment-batches.index', ['company_id' => $c]) }}">← Hủy</a>@endsection

@section('content')
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('admin.payroll.payment-batches.index', ['company_id' => $c]) }}">Payment Batches</a><i>/</i><span>Tạo mới</span><i>/</i>

         <span>
            Payroll/resources/views/payment-batches/create
        </span></nav>
    <div class="operation-layout">
        <section class="panel page-hero">
            <span class="eyebrow">Finalized payroll only</span>
            <h2>Tạo payment instruction snapshot</h2>
            <p>Hệ thống lấy chính xác net-after-tax từ persisted Payroll Item. Người dùng không được nhập hoặc sửa amount tại màn hình này.</p>
            <div class="hero-actions"><span class="security-note">◆ Idempotency và active intent bảo vệ chống tạo lệnh thanh toán trùng.</span></div>
        </section>
        <aside class="section-card">
            <div class="section-head"><div><h3>Điều kiện bắt buộc</h3><p>Run không đạt điều kiện sẽ bị từ chối.</p></div></div>
            <div class="primary-cell"><strong>01 · Finalized Run</strong><span>Đúng company và calculation version</span></div><hr style="border:0;border-top:1px solid var(--line);margin:15px 0">
            <div class="primary-cell"><strong>02 · Verified accounts</strong><span>Active, effective và cùng currency</span></div><hr style="border:0;border-top:1px solid var(--line);margin:15px 0">
            <div class="primary-cell"><strong>03 · Positive net amount</strong><span>Zero excluded, negative blocked</span></div>
        </aside>
    </div>
    <section class="section-card" style="max-width:760px">
        <div class="section-head"><div><h3>Nguồn Payroll Run</h3><p>Nhập ID của Run đã finalized để tạo batch bất biến.</p></div></div>
        @if($errors->any())<div class="validation-summary"><strong>Không thể tạo Payment Batch.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('admin.payroll.payment-batches.store') }}">
            @csrf
            <input type="hidden" name="company_id" value="{{ $c }}">
            <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            <div class="form-grid">
                <label class="span-2">Finalized Payroll Run ID<input type="number" min="1" name="run_id" required value="{{ old('run_id') }}" placeholder="Ví dụ: 1024"><span class="form-help">Amount, employee và account snapshot sẽ được resolve ở backend.</span></label>
                <div class="span-2"><button class="button" type="submit">Tạo immutable Payment Batch</button></div>
            </div>
        </form>
    </section>
@endsection
