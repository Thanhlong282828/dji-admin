@extends('layouts.admin')

@section('title', 'Payment Batch')

@section('header_action')
    <a class="button secondary" href="{{ route('admin.payroll.payment-batches.index', ['company_id' => $c]) }}">← Danh sách batch</a>
@endsection

@section('content')
    @php
        $batch = $vm->detail;
        $states = ['draft', 'validated', 'reviewed', 'approved', 'released'];
        $currentIndex = array_search($batch['status'], $states, true);
    @endphp
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('admin.payroll.payment-batches.index', ['company_id' => $c]) }}">Payment Batches</a><i>/</i><span>{{ $batch['batch_number'] }}</span><i>/</i>

         <span>
            Payroll/resources/views/payment-batches/show
        </span></nav>

    <section class="panel page-hero">
        <span class="status-pill is-{{ $batch['status'] }}">{{ $batch['status'] }}</span>
        <h2>{{ $batch['batch_number'] }}</h2>
        <p>Run {{ $batch['run_number'] }} · Kỳ {{ $batch['period_code'] }} · Calculation version {{ $batch['calculation_version'] }}</p>
        @if($batch['status'] !== 'rejected')
            <div class="state-track" aria-label="Tiến trình payment batch">
                @foreach($states as $index => $state)
                    <span class="state-step {{ is_int($currentIndex) && $index < $currentIndex ? 'done' : '' }} {{ $batch['status'] === $state ? 'current' : '' }}">{{ $state }}</span>
                @endforeach
            </div>
        @endif
    </section>

    <section class="summary-grid">
        <article class="summary-card"><span>Nhân viên đủ điều kiện</span><strong>{{ number_format($batch['employee_count']) }}</strong><small>{{ $batch['excluded_count'] }} excluded · {{ $batch['failed_count'] }} failed</small></article>
        <article class="summary-card"><span>Tổng instruction</span><strong>{{ number_format($batch['total_amount']) }}</strong><small>{{ $batch['currency'] }} · integer minor units</small></article>
        <article class="summary-card"><span>Ngày thanh toán</span><strong>{{ $batch['payment_date'] }}</strong><small>Không đồng nghĩa bank settlement</small></article>
        <article class="summary-card"><span>Calculation source</span><strong>V{{ $batch['calculation_version'] }}</strong><small>Persisted finalized snapshot</small></article>
    </section>

    <div class="operation-layout">
        <div class="operation-main">
            @if($batch['issues'])
                <section class="section-card">
                    <div class="section-head"><div><h3>Validation issues</h3><p>Các vấn đề phải được xử lý trước khi tiếp tục lifecycle.</p></div></div>
                    <div class="issue-list">
                        @foreach($batch['issues'] as $issue)
                            <div class="issue-row"><strong>{{ $issue['code'] }} · {{ strtoupper($issue['severity']) }}</strong><span>{{ $issue['message'] }}</span></div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="table-shell">
                <div class="section-head" style="padding:22px 22px 0"><div><h3>Payment instructions</h3><p>Beneficiary snapshot đã mask; export sử dụng ciphertext snapshot bất biến.</p></div><span class="status-pill is-{{ $batch['status'] }}">{{ count($batch['items']) }} items</span></div>
                @if($batch['items'] === [])
                    <div class="empty-block"><div class="empty-icon">0</div><h3>Không có instruction hợp lệ</h3><p>Kiểm tra validation issues hoặc nguồn Payroll Run.</p></div>
                @else
                    <div class="table-scroll"><table class="data-table"><thead><tr><th>Nhân viên</th><th>Bank/account</th><th>Reference</th><th>Số tiền</th><th>Trạng thái</th></tr></thead><tbody>
                    @foreach($batch['items'] as $item)
                        <tr>
                            <td class="primary-cell"><strong>{{ $item['employee_name'] }}</strong><span>{{ $item['employee_number'] }}</span></td>
                            <td class="primary-cell"><strong>{{ $item['bank'] ?? '—' }}</strong><span class="masked-number">{{ $item['masked_account'] }}</span></td>
                            <td><span class="masked-number">{{ $item['reference'] }}</span></td>
                            <td class="money">{{ number_format($item['amount']) }} <small>{{ $item['currency'] }}</small></td>
                            <td><span class="status-pill is-{{ $item['status'] }}">{{ $item['status'] }}</span><small>{{ $item['reconciliation_status'] }}</small></td>
                        </tr>
                    @endforeach
                    </tbody></table></div>
                @endif
            </section>
        </div>

        <aside class="operation-side">
            <section class="section-card">
                <div class="section-head"><div><h3>Hành động tiếp theo</h3><p>Hiển thị theo permission và state.</p></div></div>
                <div class="action-stack">
                    @if($batch['status'] === 'draft')
                        @can('payroll.payment.batch.review')<form method="POST" action="{{ route('admin.payroll.payment-batches.validate', ['batch' => $batch['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button">Validate batch</button></form>@endcan
                    @elseif($batch['status'] === 'validated')
                        @can('payroll.payment.batch.review')<form method="POST" action="{{ route('admin.payroll.payment-batches.review', ['batch' => $batch['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button">Maker review</button></form>@endcan
                    @elseif($batch['status'] === 'reviewed')
                        @can('payroll.payment.batch.approve')<form method="POST" action="{{ route('admin.payroll.payment-batches.approve', ['batch' => $batch['id']]) }}">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button">Checker approve</button></form>@endcan
                    @elseif($batch['status'] === 'approved')
                        @can('payroll.payment.batch.release')<form method="POST" action="{{ route('admin.payroll.payment-batches.release', ['batch' => $batch['id']]) }}" onsubmit="return confirm('Phát hành file private? Thao tác này KHÔNG chuyển tiền qua ngân hàng.');">@csrf<input type="hidden" name="company_id" value="{{ $c }}"><button class="button">Release private CSV</button></form>@endcan
                    @elseif($batch['status'] === 'released')
                        @can('payroll.payment.batch.download')<a class="button" href="{{ route('admin.payroll.payment-batches.download', ['batch' => $batch['id'], 'company_id' => $c]) }}">Secure download</a>@endcan
                    @else
                        <span class="security-note">Batch này chỉ cho phép xem theo lifecycle hiện tại.</span>
                    @endif
                </div>
            </section>

            <section class="section-card">
                <div class="section-head"><div><h3>File integrity</h3><p>Private export metadata</p></div></div>
                <div class="primary-cell"><strong>{{ $batch['file_name'] ?? 'Chưa phát hành' }}</strong><span>{{ $batch['released_at'] ?? 'Awaiting approved state' }}</span></div>
                @if($batch['checksum'])<p class="masked-number" style="word-break:break-all">SHA-256 {{ $batch['checksum'] }}</p>@endif
            </section>
            <div class="security-note">◆ Released ≠ Paid. Hệ thống chưa kết nối trực tiếp ngân hàng và chưa nhận settlement confirmation.</div>
        </aside>
    </div>
@endsection
