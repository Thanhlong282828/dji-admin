@extends('layouts.admin')
@section('title', 'Cổng thông tin nhân viên')
@section('content')
<section class="panel portal-hero">
    <span class="eyebrow">EMPLOYEE SELF-SERVICE</span>
    <h1>{{ $employee->full_name }}</h1>
    <p>{{ $employee->employee_code }} · {{ $assignment?->position_title ?? 'Chưa có vị trí' }} · {{ $assignment?->unit_name ?? 'Chưa có đơn vị' }}</p>
    <div class="stats">
        <article><span>Trạng thái</span><strong>{{ $employee->employment_status }}</strong></article>
        <article><span>Quản lý</span><strong>{{ $assignment?->manager_name ?? '—' }}</strong></article>
        <article><span>Chi nhánh</span><strong>{{ count($branches) }}</strong></article>
        <article><span>Thông báo chưa đọc</span><strong>{{ $notificationCount }}</strong></article>
    </div>
</section>

<section class="panel">
    <h2>Chấm công hôm nay</h2>
    <p>Thao tác chỉ áp dụng cho chính tài khoản đang đăng nhập.</p>
    <div class="actions">
        @can('attendance.check-in')
            <form method="POST" action="{{ route('admin.attendance.check-in') }}">@csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="source" value="web"><input type="hidden" name="deduplication_key" value="{{ Illuminate\Support\Str::uuid() }}"><button>Check-in</button>
            </form>
        @endcan
        @can('attendance.check-out')
            <form method="POST" action="{{ route('admin.attendance.check-out') }}">@csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="source" value="web"><input type="hidden" name="deduplication_key" value="{{ Illuminate\Support\Str::uuid() }}"><button>Check-out</button>
            </form>
        @endcan
    </div>
</section>

<section class="panel"><h2>Chi nhánh được cấp quyền</h2><div class="card-grid">@forelse($branches as $branch)<article><strong>{{ $branch['name'] }}</strong><p>{{ $branch['address_line'] ?: 'Chưa có địa chỉ' }}{{ $branch['city'] ? ' · '.$branch['city'] : '' }}</p></article>@empty<p>Chưa gán chi nhánh.</p>@endforelse</div></section>

<section class="panel"><h2>Chấm công gần đây</h2><div class="table-wrap"><table><thead><tr><th>Ngày</th><th>Check-in</th><th>Check-out</th><th>Phút làm</th><th>Đi trễ</th><th>Trạng thái</th></tr></thead><tbody>@forelse($attendance as $row)<tr><td>{{ $row['work_date'] }}</td><td>{{ $row['check_in_at'] ?? '—' }}</td><td>{{ $row['check_out_at'] ?? '—' }}</td><td>{{ $row['worked_minutes'] }}</td><td>{{ $row['late_minutes'] }}</td><td>{{ $row['approval_status'] }}</td></tr>@empty<tr><td colspan="6">Chưa có dữ liệu chấm công.</td></tr>@endforelse</tbody></table></div></section>

<div class="two-column-grid">
    <section class="panel"><h2>Nghỉ phép</h2>@forelse($leave as $row)<article><strong>{{ $row['leave_type'] }}</strong><p>{{ $row['starts_at'] }} → {{ $row['ends_at'] }} · {{ $row['status'] }}</p></article>@empty<p>Chưa có yêu cầu nghỉ.</p>@endforelse</section>
    <section class="panel"><h2>Tăng ca</h2>@forelse($overtime as $row)<article><strong>{{ $row['work_date'] }}</strong><p>Yêu cầu {{ $row['requested_minutes'] }} phút · Được tính {{ $row['payable_minutes'] }} phút · {{ $row['status'] }}</p></article>@empty<p>Chưa có yêu cầu tăng ca.</p>@endforelse</section>
</div>

<section class="panel"><h2>Lương của tôi</h2><p class="muted">Chỉ hiển thị kết quả Payroll đã đủ điều kiện; không thay đổi Payroll lịch sử.</p><div class="table-wrap"><table><thead><tr><th>Kỳ</th><th>Gross</th><th>Bảo hiểm NLĐ</th><th>PIT</th><th>Khấu trừ</th><th>Net</th><th>Trạng thái</th></tr></thead><tbody>@forelse($payroll as $row)<tr><td>{{ $row['period_start'] }} → {{ $row['period_end'] }}</td><td>{{ number_format((int) $row['gross_amount_minor'], 0, ',', '.') }} ₫</td><td>{{ number_format((int) $row['insurance_employee_total_minor'], 0, ',', '.') }} ₫</td><td>{{ number_format((int) $row['personal_income_tax_minor'], 0, ',', '.') }} ₫</td><td>{{ number_format((int) $row['deduction_amount_minor'], 0, ',', '.') }} ₫</td><td>{{ number_format((int) $row['net_amount_minor'], 0, ',', '.') }} ₫</td><td>{{ $row['calculation_status'] }}</td></tr>@empty<tr><td colspan="7">Chưa có Payroll Run đủ điều kiện.</td></tr>@endforelse</tbody></table></div></section>

<section class="panel"><h2>Phiếu lương</h2>@forelse($payslips as $row)<article><strong>{{ $row['period_code'] }}</strong><p>{{ $row['status'] }} · {{ $row['period_start'] }} → {{ $row['period_end'] }}</p></article>@empty<p>Chưa có phiếu lương đã phát hành.</p>@endforelse</section>
@endsection
