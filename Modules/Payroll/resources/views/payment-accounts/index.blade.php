@extends('layouts.admin')

@section('title', 'Payment Accounts')

@section('header_action')
    @can('payroll.payment.view')
        <a class="button secondary" href="{{ route('admin.payroll.payment-batches.index', ['company_id' => $c]) }}">Xem Payment Batches</a>
    @endcan
@endsection

@section('content')
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('admin.payroll.runs.index', ['company_id' => $c]) }}">Payroll</a><i>/</i><span>Payment Accounts</span></nav>
    <section class="panel page-hero">
        <span class="eyebrow">Secure beneficiary vault</span>
        <h2>Tài khoản nhận lương</h2>
        <p>Số tài khoản được mã hóa khi lưu trữ. Giao diện chỉ nhận bốn số cuối, trạng thái xác minh và khoảng hiệu lực cần thiết cho Payment Batch.</p>
        <div class="hero-actions"><span class="security-note">◆ Không có plaintext account number trong HTML, list query hoặc broadcast payload.</span></div>
    </section>

    <section class="table-shell">
        @if($vm->items === [])
            <div class="empty-block"><div class="empty-icon">••</div><h3>Chưa có tài khoản thanh toán</h3><p>Tài khoản được thêm từ hồ sơ từng nhân viên. Không có dữ liệu production nào được tự động tạo.</p></div>
        @else
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Nhân viên</th><th>Ngân hàng</th><th>Tài khoản</th><th>Chủ tài khoản</th><th>Tiền tệ</th><th>Xác minh</th><th>Hiệu lực</th><th></th></tr></thead>
                    <tbody>
                    @foreach($vm->items as $account)
                        <tr>
                            <td class="primary-cell"><strong>{{ $account['employee_name'] }}</strong><span>{{ $account['employee_number'] }}</span></td>
                            <td class="primary-cell"><strong>{{ $account['bank'] ?? 'Chưa khai báo' }}</strong><span>{{ $account['branch'] ?? 'Không có mã chi nhánh' }}</span></td>
                            <td><span class="masked-number">{{ $account['masked_account'] }}</span></td>
                            <td>{{ $account['holder'] }}</td>
                            <td>{{ $account['currency'] }}</td>
                            <td><span class="status-pill is-{{ $account['verification'] }}">{{ $account['verification'] }}</span></td>
                            <td class="primary-cell"><strong>{{ $account['effective_from'] }}</strong><span>đến {{ $account['effective_to'] ?? 'không giới hạn' }}</span></td>
                            <td><a class="row-action" href="{{ route('admin.payroll.payment-accounts.show', ['employee' => $account['employee_id'], 'company_id' => $c]) }}">Quản lý →</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
