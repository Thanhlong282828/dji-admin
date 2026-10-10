<?php

namespace App\Support\Employee;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class EmployeePortalReadService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string, mixed> */
    public function forUser(int $userId): array
    {
        $employee = $this->db->table('employees as e')
            ->where('e.user_id', $userId)
            ->whereNull('e.deleted_at')
            ->select('e.id', 'e.company_id', 'e.employee_code', 'e.full_name', 'e.work_email', 'e.phone', 'e.avatar_url', 'e.employment_status', 'e.join_date')
            ->first();
        abort_if($employee === null, 404, 'Tài khoản chưa được liên kết với hồ sơ nhân viên.');

        $employeeId = $this->integer($employee, 'id');
        $companyId = $this->integer($employee, 'company_id');
        $assignment = $this->db->table('employee_assignments as a')
            ->join('organization_units as u', 'u.id', '=', 'a.organization_unit_id')
            ->join('job_positions as p', 'p.id', '=', 'a.job_position_id')
            ->leftJoin('employees as m', 'm.id', '=', 'a.manager_id')
            ->where('a.employee_id', $employeeId)
            ->where('a.assignment_type', 'primary')
            ->whereDate('a.effective_from', '<=', today())
            ->where(fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', today()))
            ->orderByDesc('a.effective_from')
            ->select('u.name as unit_name', 'p.title as position_title', 'p.career_level', 'm.full_name as manager_name', 'a.work_location', 'a.effective_from')
            ->first();

        $branches = $this->db->table('branch_user_access as bua')
            ->join('branches as b', 'b.id', '=', 'bua.branch_id')
            ->where('bua.user_id', $userId)
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->orderBy('b.name')
            ->get(['b.id', 'b.code', 'b.name', 'b.address_line', 'b.city', 'b.status'])
            ->map(static fn (object $row): array => (array) $row)->all();

        $attendance = $this->db->table('attendance_records')
            ->where('company_id', $companyId)->where('employee_id', $employeeId)
            ->orderByDesc('work_date')->limit(14)
            ->get(['id', 'work_date', 'check_in_at', 'check_out_at', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'attendance_status', 'approval_status'])
            ->map(static fn (object $row): array => (array) $row)->all();
        $todayAttendance = $this->db->table('attendance_records as ar')
            ->leftJoin('work_shifts as ws', 'ws.id', '=', 'ar.work_shift_id')
            ->where('ar.company_id', $companyId)->where('ar.employee_id', $employeeId)->whereDate('ar.work_date', today())
            ->select(['ar.id', 'ar.work_date', 'ar.check_in_at', 'ar.check_out_at', 'ar.scheduled_minutes', 'ar.worked_minutes', 'ar.late_minutes', 'ar.early_leave_minutes', 'ar.overtime_minutes', 'ar.attendance_status', 'ar.approval_status', 'ar.calculation_status', 'ws.name as shift_name', 'ws.starts_at as shift_start', 'ws.ends_at as shift_end'])
            ->first();
        $todayLeave = $this->db->table('leave_requests as lr')->join('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
            ->where('lr.company_id', $companyId)->where('lr.employee_id', $employeeId)->where('lr.status', 'approved')
            ->whereDate('lr.starts_at', '<=', today())->whereDate('lr.ends_at', '>=', today())
            ->select(['lr.id', 'lt.name as leave_type', 'lr.requested_minutes'])->first();
        $leave = $this->db->table('leave_requests as r')->join('leave_types as t', 't.id', '=', 'r.leave_type_id')
            ->where('r.company_id', $companyId)->where('r.employee_id', $employeeId)
            ->orderByDesc('r.starts_at')->limit(10)
            ->get(['r.id', 't.name as leave_type', 'r.starts_at', 'r.ends_at', 'r.requested_minutes', 'r.status'])
            ->map(static fn (object $row): array => (array) $row)->all();
        $overtime = $this->db->table('overtime_requests')
            ->where('company_id', $companyId)->where('employee_id', $employeeId)
            ->orderByDesc('work_date')->limit(10)
            ->get(['id', 'work_date', 'requested_minutes', 'approved_minutes', 'payable_minutes', 'status'])
            ->map(static fn (object $row): array => (array) $row)->all();
        $payroll = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->where('i.company_id', $companyId)->where('i.employee_id', $employeeId)
            ->whereIn('r.status', ['approved', 'finalized', 'locked', 'paid'])
            ->orderByDesc('r.period_end')->limit(12)
            ->get(['i.id', 'r.code as run_code', 'r.status as run_status', 'r.period_start', 'r.period_end', 'i.currency_code', 'i.scheduled_minutes', 'i.worked_minutes', 'i.paid_leave_minutes', 'i.unpaid_leave_minutes', 'i.approved_overtime_minutes', 'i.gross_amount_minor', 'i.insurance_employee_total_minor', 'i.personal_income_tax_minor', 'i.deduction_amount_minor', 'i.net_amount_minor', 'i.calculation_status'])
            ->map(static fn (object $row): array => (array) $row)->all();
        foreach ($payroll as $index => &$movement) {
            $previous = $payroll[$index + 1] ?? null;
            $movement['net_delta_minor'] = is_array($previous) ? $this->arrayInteger($movement, 'net_amount_minor') - $this->arrayInteger($previous, 'net_amount_minor') : null;
            $movement['attendance_payroll_treatment'] = 'NOT_SEPARATELY_ATTRIBUTABLE';
        }
        unset($movement);
        $payslips = $this->db->table('payroll_payslips as p')->join('payroll_periods as pp', 'pp.id', '=', 'p.payroll_period_id')
            ->where('p.company_id', $companyId)->where('p.employee_id', $employeeId)
            ->whereIn('p.status', ['generated', 'published'])
            ->orderByDesc('pp.period_end')->limit(12)
            ->get(['p.id', 'p.uuid', 'pp.code as period_code', 'pp.period_start', 'pp.period_end', 'p.status', 'p.published_at'])
            ->map(static fn (object $row): array => (array) $row)->all();
        $notificationCount = $this->db->table('notifications')->where('notifiable_type', 'App\\Models\\User')->where('notifiable_id', $userId)->whereNull('read_at')->count();

        return compact('employee', 'assignment', 'branches', 'attendance', 'todayAttendance', 'todayLeave', 'leave', 'overtime', 'payroll', 'payslips', 'notificationCount', 'companyId');
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param array<array-key, mixed> $row */
    private function arrayInteger(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}
