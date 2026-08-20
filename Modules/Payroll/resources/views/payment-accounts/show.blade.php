@extends('layouts.admin')

@section('title', 'Payment Account')
@section('header_action')<a class="button secondary" href="{{ route('admin.payroll.payment-accounts.index', ['company_id' => $c]) }}">← Danh sách tài khoản</a>@endsection

@section('content')
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('admin.payroll.payment-accounts.index', ['company_id' => $c]) }}">Payment Accounts</a><i>/</i><span>Employee #{{ $employee }}</span>           <i>/</i>

         <span>
            Payroll/resources/views/payment-accounts/show
        </span> </nav>
    <section class="panel page-hero">
        <span class="eyebrow">Encrypted beneficiary profile</span>
        <h2>Tài khoản thanh toán nhân viên</h2>
        <p>Chỉ bốn số cuối được đưa vào HTML. Khi thay account number, hệ thống mã hóa lại và tự động yêu cầu checker xác minh lại.</p>
    </section>

    <div class="operation-layout">
        <div class="operation-main">
            <section class="section-card">
                <div class="section-head"><div><h3>Tài khoản hiện có</h3><p>Employee ID {{ $employee }}</p></div><span class="status-pill is-active">{{ count($vm->items) }} accounts</span></div>
                @forelse($vm->items as $account)
                    <article class="summary-card" style="margin-bottom:12px">
                        <div class="section-head">
                            <div class="primary-cell"><strong>{{ $account['bank'] ?? 'Ngân hàng chưa khai báo' }}</strong><span>{{ $account['branch'] ?? 'Không có mã chi nhánh' }}</span></div>
                            <span class="status-pill is-{{ $account['verification'] }}">{{ $account['verification'] }}</span>
                        </div>
                        <strong class="masked-number" style="font-size:25px">{{ $account['masked_account'] }}</strong>
                        <p>{{ $account['holder'] }} · {{ $account['currency'] }} · {{ $account['effective_from'] }} → {{ $account['effective_to'] ?? 'không giới hạn' }}</p>
                        @if($account['verification'] !== 'verified')
                            @can('payroll.payment.account.verify')
                                <form method="POST" action="{{ route('admin.payroll.payment-accounts.verify', ['account' => $account['id']]) }}" onsubmit="return confirm('Xác nhận đã đối chiếu tài khoản nhận lương này?');">
                                    @csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button" type="submit">Checker xác minh</button>
                                </form>
                            @endcan
                        @endif
                    </article>
                @empty
                    <div class="empty-block"><div class="empty-icon">••</div><h3>Chưa có tài khoản</h3><p>Điền biểu mẫu bên cạnh để lưu account đầu tiên dưới dạng ciphertext.</p></div>
                @endforelse
            </section>
        </div>

        <aside class="operation-side">
            @can('payroll.payment.account.manage')
                <form class="section-card" method="POST" action="{{ route('admin.payroll.payment-accounts.store', ['employee' => $employee]) }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $c }}"><input type="hidden" name="employee_id" value="{{ $employee }}"><input type="hidden" name="payment_method" value="bank_transfer"><input type="hidden" name="is_primary" value="1"><input type="hidden" name="status" value="active">
                    <div class="section-head"><div><h3>Thêm tài khoản</h3><p>Primary bank transfer account</p></div></div>
                    @if($errors->any())<div class="validation-summary"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <div class="form-grid">
                        <label>Bank code<input name="bank_code" required value="{{ old('bank_code') }}" placeholder="VCB"></label>
                        <label>Branch code<input name="branch_code" value="{{ old('branch_code') }}" placeholder="HCM"></label>
                        <label class="span-2">Bank name<input name="bank_name" value="{{ old('bank_name') }}" placeholder="Tên ngân hàng"></label>
                        <label class="span-2">Account number<input name="account_number" required autocomplete="new-password" inputmode="numeric" placeholder="Không được hiển thị lại"><span class="form-help">Được mã hóa ngay tại write boundary.</span></label>
                        <label class="span-2">Account holder<input name="account_holder_name" required value="{{ old('account_holder_name') }}"></label>
                        <label>Currency<input name="currency" required maxlength="3" value="{{ old('currency', 'VND') }}"></label>
                        <label>Effective from<input type="date" name="effective_from" required value="{{ old('effective_from', now()->toDateString()) }}"></label>
                        <div class="span-2"><button class="button" type="submit">Lưu tài khoản mã hóa</button></div>
                    </div>
                </form>
            @endcan
            <div class="security-note">◆ UI không preload full account number. Verification được reset khi account number thay đổi.</div>
        </aside>
    </div>
@endsection
