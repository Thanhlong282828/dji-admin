@extends('layouts.admin')
@section('title', 'Cổng thông tin nhân viên')
@section('content')
@php
    $todayStatus = $todayLeave ? 'ON_LEAVE' : ($todayAttendance?->attendance_status ? strtoupper((string) $todayAttendance->attendance_status) : 'NOT_RECORDED');
    $checkIn = $todayAttendance?->check_in_at ? \Carbon\CarbonImmutable::parse($todayAttendance->check_in_at)->format('H:i') : '—';
    $checkOut = $todayAttendance?->check_out_at ? \Carbon\CarbonImmutable::parse($todayAttendance->check_out_at)->format('H:i') : '—';
@endphp

<section class="panel portal-hero">
    <span class="eyebrow">EMPLOYEE SELF-SERVICE</span>
    <h2>{{ $employee->full_name }}</h2>
    <p>{{ $employee->employee_code }} · {{ $assignment?->position_title ?? 'Chưa có vị trí' }} · {{ $assignment?->unit_name ?? 'Chưa có đơn vị' }}</p>
    <div class="erp-kpi-grid erp-kpi-grid--compact">
        <x-erp.kpi-card label="Trạng thái" :value="strtoupper((string) $employee->employment_status)" />
        <x-erp.kpi-card label="Quản lý" :value="$assignment?->manager_name ?? '—'" />
        <x-erp.kpi-card label="Chi nhánh được cấp" :value="number_format(count($branches))" />
        <x-erp.kpi-card label="Thông báo chưa đọc" :value="number_format($notificationCount)" />
    </div>
</section>

<section class="admin-panel" data-refresh-scope="employee-attendance" data-refresh-url="{{ request()->fullUrl() }}">
    <div class="admin-panel__header"><div><p class="eyebrow">TODAY · {{ now()->format('d/m/Y') }}</p><h2>Chấm công hôm nay</h2><p>Thời gian được xác lập tại server; thao tác chỉ áp dụng cho chính tài khoản đang đăng nhập.</p></div><span class="status-badge">{{ $todayStatus }}</span></div>
    <div class="erp-kpi-grid">
        <x-erp.kpi-card label="Ca làm việc" :value="$todayAttendance?->shift_name ?? 'CHƯA XÁC ĐỊNH'" :meta="$todayAttendance?->shift_start && $todayAttendance?->shift_end ? substr((string) $todayAttendance->shift_start, 0, 5).' – '.substr((string) $todayAttendance->shift_end, 0, 5) : 'Không suy đoán lịch làm việc'" />
        <x-erp.kpi-card label="Check-in" :value="$checkIn" />
        <x-erp.kpi-card label="Check-out" :value="$checkOut" />
        <x-erp.kpi-card label="Thời gian làm" :value="number_format((int) ($todayAttendance?->worked_minutes ?? 0)).' phút'" />
        <x-erp.kpi-card label="Đi trễ" :value="number_format((int) ($todayAttendance?->late_minutes ?? 0)).' phút'" />
        <x-erp.kpi-card label="Về sớm" :value="number_format((int) ($todayAttendance?->early_leave_minutes ?? 0)).' phút'" />
        <x-erp.kpi-card label="Tăng ca được ghi nhận" :value="number_format((int) ($todayAttendance?->overtime_minutes ?? 0)).' phút'" />
        <x-erp.kpi-card label="Nghỉ phép" :value="$todayLeave?->leave_type ?? 'KHÔNG CÓ'" :meta="$todayLeave ? 'Đã duyệt theo Leave owner' : 'Không có leave được duyệt trong ngày'" />
    </div>
    <div class="erp-evidence-note"><strong>Ảnh chấm công:</strong> NOT_IMPLEMENTED · Media pipeline đã có nhưng Attendance chưa có evidence contract; hệ thống không giả vờ đã lưu ảnh.</div>
    <div class="actions">
        @can('attendance.check-in')
            <form method="POST" action="{{ route('admin.attendance.check-in') }}">@csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="source" value="web"><input type="hidden" name="deduplication_key" value="{{ Illuminate\Support\Str::uuid() }}"><button class="button" type="submit">Check-in</button>
            </form>
        @endcan
        @can('attendance.check-out')
            <form method="POST" action="{{ route('admin.attendance.check-out') }}">@csrf
                <input type="hidden" name="company_id" value="{{ $companyId }}"><input type="hidden" name="source" value="web"><input type="hidden" name="deduplication_key" value="{{ Illuminate\Support\Str::uuid() }}"><button class="button" type="submit">Check-out</button>
            </form>
        @endcan
        @can('attendance.view-own')<a class="button ghost" href="{{ route('admin.attendance.index', ['company_id' => $companyId]) }}">Lịch sử chấm công</a>@endcan
    </div>
    <p class="muted">QR challenge: NOT_IMPLEMENTED. Không dùng QR tĩnh hoặc token có thể phát lại.</p>
</section>

<section class="admin-panel"><div class="admin-panel__header"><div><h2>Lịch sử chấm công</h2><p>Attendance fact và trạng thái duyệt từ owner module; không tự suy diễn ảnh hưởng lương.</p></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Ngày</th><th>Check-in</th><th>Check-out</th><th class="numeric">Phút làm</th><th class="numeric">Đi trễ</th><th>Attendance</th><th>Duyệt</th></tr></thead><tbody>@forelse($attendance as $row)<tr><td><a href="{{ route('admin.attendance.show', ['record' => $row['id'], 'company_id' => $companyId]) }}">{{ $row['work_date'] }}</a></td><td>{{ $row['check_in_at'] ? \Carbon\CarbonImmutable::parse($row['check_in_at'])->format('H:i') : '—' }}</td><td>{{ $row['check_out_at'] ? \Carbon\CarbonImmutable::parse($row['check_out_at'])->format('H:i') : '—' }}</td><td class="numeric">{{ number_format((int) $row['worked_minutes']) }}</td><td class="numeric">{{ number_format((int) $row['late_minutes']) }}</td><td><span class="status-badge">{{ strtoupper((string) $row['attendance_status']) }}</span></td><td>{{ strtoupper((string) $row['approval_status']) }}</td></tr>@empty<tr><td colspan="7"><div class="empty-state"><strong>Chưa có dữ liệu chấm công</strong><p>Không tạo attendance giả để lấp dashboard.</p></div></td></tr>@endforelse</tbody></table></div></section>

<div class="two-column-grid">
    <section class="admin-panel"><div class="admin-panel__header"><h2>Nghỉ phép</h2></div>@forelse($leave as $row)<article class="data-panel"><strong>{{ $row['leave_type'] }}</strong><p>{{ $row['starts_at'] }} → {{ $row['ends_at'] }} · <span class="status-badge">{{ strtoupper((string) $row['status']) }}</span></p></article>@empty<div class="empty-state">Chưa có yêu cầu nghỉ.</div>@endforelse</section>
    <section class="admin-panel"><div class="admin-panel__header"><h2>Tăng ca</h2></div>@forelse($overtime as $row)<article class="data-panel"><strong>{{ $row['work_date'] }}</strong><p>Yêu cầu {{ number_format((int) $row['requested_minutes']) }} phút · Được tính {{ number_format((int) $row['payable_minutes']) }} phút · <span class="status-badge">{{ strtoupper((string) $row['status']) }}</span></p></article>@empty<div class="empty-state">Chưa có yêu cầu tăng ca.</div>@endforelse</section>
</div>

<section class="admin-panel"><div class="admin-panel__header"><div><h2>Biến động lương</h2><p>Chỉ dùng Payroll Item đủ điều kiện. Chênh lệch so với kỳ finalized/approved trước; không gọi draft forecast là lương thực nhận.</p></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Kỳ</th><th>Ngày công</th><th>OT</th><th class="numeric">Gross</th><th class="numeric">Bảo hiểm</th><th class="numeric">PIT</th><th class="numeric">Khấu trừ</th><th class="numeric">Net</th><th class="numeric">Δ Net</th><th>Attendance → Payroll</th></tr></thead><tbody>@forelse($payroll as $row)<tr><td><strong>{{ $row['run_code'] }}</strong><br><small>{{ $row['period_start'] }} → {{ $row['period_end'] }} · {{ strtoupper((string) $row['run_status']) }}</small></td><td>{{ number_format((int) $row['worked_minutes']) }}/{{ number_format((int) $row['scheduled_minutes']) }} phút<br><small>Paid leave {{ number_format((int) $row['paid_leave_minutes']) }} · Unpaid {{ number_format((int) $row['unpaid_leave_minutes']) }}</small></td><td>{{ number_format((int) $row['approved_overtime_minutes']) }} phút</td><td class="numeric">{{ number_format((int) $row['gross_amount_minor'], 0, ',', '.') }} {{ $row['currency_code'] }}</td><td class="numeric">{{ number_format((int) $row['insurance_employee_total_minor'], 0, ',', '.') }}</td><td class="numeric">{{ number_format((int) $row['personal_income_tax_minor'], 0, ',', '.') }}</td><td class="numeric">{{ number_format((int) $row['deduction_amount_minor'], 0, ',', '.') }}</td><td class="numeric"><strong>{{ number_format((int) $row['net_amount_minor'], 0, ',', '.') }}</strong></td><td class="numeric">{{ $row['net_delta_minor'] === null ? 'Kỳ gốc' : (($row['net_delta_minor'] >= 0 ? '+' : '').number_format((int) $row['net_delta_minor'], 0, ',', '.')) }}</td><td><span class="readiness-badge readiness-badge--partial">{{ $row['attendance_payroll_treatment'] }}</span><br><small>Không có component attribution riêng; không quy đổi phút trễ thành tiền.</small></td></tr>@empty<tr><td colspan="10"><div class="empty-state"><strong>Chưa có Payroll Run đủ điều kiện</strong><p>Không hiển thị draft hoặc tự tính tiền lương.</p></div></td></tr>@endforelse</tbody></table></div></section>

<section class="admin-panel"><div class="admin-panel__header"><h2>Phiếu lương</h2></div><div class="card-grid">@forelse($payslips as $row)<article class="data-panel"><strong>{{ $row['period_code'] }}</strong><p><span class="status-badge">{{ strtoupper((string) $row['status']) }}</span> · {{ $row['period_start'] }} → {{ $row['period_end'] }}</p></article>@empty<div class="empty-state">Chưa có phiếu lương đã phát hành.</div>@endforelse</div></section>

<section class="admin-panel"><div class="admin-panel__header"><h2>Chi nhánh được cấp quyền</h2></div><div class="card-grid">@forelse($branches as $branch)<article class="data-panel"><strong>{{ $branch['name'] }}</strong><p>{{ $branch['address_line'] ?: 'Chưa có địa chỉ' }}{{ $branch['city'] ? ' · '.$branch['city'] : '' }}</p></article>@empty<div class="empty-state">Chưa gán chi nhánh.</div>@endforelse</div></section>
@endsection
