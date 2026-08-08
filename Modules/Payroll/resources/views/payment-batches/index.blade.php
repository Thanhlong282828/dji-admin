@extends('layouts.admin')

@section('title', 'Payment Batches')

@section('header_action')
    @can('payroll.payment.batch.create')
        <a class="button" href="{{ route('admin.payroll.payment-batches.create', ['company_id' => $c]) }}">+ Tạo payment batch</a>
    @endcan
@endsection

@section('content')
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.payroll.runs.index', ['company_id' => $c]) }}">Payroll</a><i>/</i><span>Payment Batches</span>
    </nav>

    <section class="panel page-hero">
        <span class="eyebrow">Bank payment foundation</span>
        <h2>Kiểm soát lệnh thanh toán</h2>
        <p>Theo dõi payment instruction từ Payroll Run đã khóa đến file CSV riêng tư. Trạng thái Released chỉ xác nhận file được phát hành, không đồng nghĩa ngân hàng đã chuyển tiền.</p>
        <div class="hero-actions">
            @can('payroll.payment.batch.create')
                <a class="button" href="{{ route('admin.payroll.payment-batches.create', ['company_id' => $c]) }}">Tạo từ finalized run</a>
            @endcan
            @can('payroll.payment.view')
                <a class="button secondary" href="{{ route('admin.payroll.payment-accounts.index', ['company_id' => $c]) }}">Quản lý tài khoản nhận</a>
            @endcan
        </div>
    </section>

    <form class="toolbar" method="GET">
        <input type="hidden" name="company_id" value="{{ $c }}">
        <label>Tìm payment batch
            <input name="search" value="{{ $f['search'] }}" placeholder="Nhập batch number…">
        </label>
        <label>Trạng thái
            <select name="status">
                <option value="">Tất cả trạng thái</option>
                @foreach(['draft' => 'Draft', 'validated' => 'Validated', 'reviewed' => 'Reviewed', 'approved' => 'Approved', 'released' => 'Released', 'rejected' => 'Rejected'] as $value => $label)
                    <option value="{{ $value }}" @selected($f['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="button" type="submit">Áp dụng bộ lọc</button>
    </form>

    <section class="table-shell">
        @if($vm->items === [])
            <div class="empty-block">
                <div class="empty-icon">₫</div>
                <h3>Chưa có Payment Batch</h3>
                <p>Hãy chọn một Payroll Run đã finalized để tạo instruction snapshot. Hệ thống không tự động tạo dữ liệu production.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Batch</th><th>Payroll source</th><th>Payment date</th><th>Nhân viên</th><th>Tổng tiền</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                    @foreach($vm->items as $batch)
                        <tr>
                            <td class="primary-cell"><strong>{{ $batch['batch_number'] }}</strong><span>Generic payment instruction</span></td>
                            <td class="primary-cell"><strong>{{ $batch['run_number'] }}</strong><span>Kỳ {{ $batch['period_code'] }}</span></td>
                            <td>{{ $batch['payment_date'] }}</td>
                            <td>{{ number_format($batch['employee_count']) }}</td>
                            <td class="money">{{ number_format($batch['total_amount']) }} <small>{{ $batch['currency'] }} · minor units</small></td>
                            <td><span class="status-pill is-{{ $batch['status'] }}">{{ $batch['status'] }}</span></td>
                            <td><a class="row-action" href="{{ route('admin.payroll.payment-batches.show', ['batch' => $batch['id'], 'company_id' => $c]) }}">Chi tiết →</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-row">
                <span>Trang {{ $vm->page }}</span>
                <div>
                    @if($vm->page > 1)<a class="button-ghost" href="{{ request()->fullUrlWithQuery(['page' => $vm->page - 1]) }}">← Trước</a>@endif
                    @if($vm->hasMore)<a class="button-ghost" href="{{ request()->fullUrlWithQuery(['page' => $vm->page + 1]) }}">Tiếp →</a>@endif
                </div>
            </div>
        @endif
    </section>
@endsection
