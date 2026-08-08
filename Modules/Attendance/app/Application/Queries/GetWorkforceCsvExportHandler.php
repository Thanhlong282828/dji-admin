<?php

namespace Modules\Attendance\Application\Queries;

use App\Support\StreamingCsvResponseFactory;
use Generator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class GetWorkforceCsvExportHandler
{
    public function __construct(private ConnectionInterface $db, private StreamingCsvResponseFactory $csv) {}

    public function handle(GetWorkforceCsvExportQuery $query): StreamedResponse
    {
        [$headings, $rows] = match ($query->type) {
            'attendance' => [$this->attendanceHeadings(), fn (): Generator => $this->attendance($query)],
            'leave' => [$this->leaveHeadings(), fn (): Generator => $this->leave($query)],
            'leave-balances' => [$this->balanceHeadings(), fn (): Generator => $this->balances($query)],
            'overtime' => [$this->overtimeHeadings(), fn (): Generator => $this->overtime($query)],
            'payroll-input' => [$this->payrollHeadings(), fn (): Generator => $this->payroll($query)],
            'workforce' => [$this->workforceHeadings(), fn (): Generator => $this->workforce($query->companyId)],
            default => abort(404),
        };
        activity('workforce-export')->causedBy($query->actorId)->event($query->type)->withProperties(['company_id' => $query->companyId, 'type' => $query->type])->log('Workforce CSV exported');

        return $this->csv->make('schema-'.$query->type.'-'.today()->format('Ymd').'.csv', $headings, $rows());
    }

    /** @return list<string> */
    private function attendanceHeadings(): array
    {
        return ['Employee Number', 'Employee Name', 'Work Date', 'Shift', 'First Check-in', 'Last Check-out', 'Worked Minutes', 'Break Minutes', 'Late Minutes', 'Early Leave Minutes', 'Overtime Minutes', 'Presence Status', 'Approval Status', 'Calculation Status', 'Anomalies', 'Lock Status'];
    }

    /** @return list<string> */
    private function leaveHeadings(): array
    {
        return ['Employee Number', 'Employee Name', 'Policy', 'Leave Type', 'Start', 'End', 'Requested Minutes', 'Status', 'Submitted At', 'Approved At', 'Attachment Status'];
    }

    /** @return list<string> */
    private function balanceHeadings(): array
    {
        return ['Employee Number', 'Employee Name', 'Policy', 'Opening Minutes', 'Accrued Minutes', 'Reserved Minutes', 'Used Minutes', 'Carried Minutes', 'Expired Minutes', 'Adjustment Minutes', 'Available Minutes'];
    }

    /** @return list<string> */
    private function overtimeHeadings(): array
    {
        return ['Employee Number', 'Employee Name', 'Work Date', 'Policy', 'Requested Start', 'Requested End', 'Requested Minutes', 'Approved Minutes', 'Actual Minutes', 'Payable Minutes', 'Status', 'Calculation Status'];
    }

    /** @return list<string> */
    private function payrollHeadings(): array
    {
        return ['Employee Number', 'Employee Name', 'Period', 'Scheduled Minutes', 'Worked Minutes', 'Paid Leave Minutes', 'Unpaid Leave Minutes', 'Approved Payable Overtime Minutes', 'Late Minutes', 'Early Leave Minutes', 'Absence Minutes', 'Snapshot Version', 'Lock Status'];
    }

    /** @return list<string> */
    private function workforceHeadings(): array
    {
        return ['Company', 'Resource Type', 'Code', 'Name', 'Status', 'Effective From', 'Effective To', 'Timezone', 'Planned Minutes', 'Default Shift'];
    }

    /** @return Generator<int,list<bool|float|int|string|null>> */
    private function attendance(GetWorkforceCsvExportQuery $query): Generator
    {
        $builder = $this->db->table('attendance_records as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('work_shifts as s', 's.id', '=', 'r.work_shift_id')->where('r.company_id', $query->companyId)
            ->when($query->employeeId !== null, fn ($b) => $b->where('r.employee_id', $query->employeeId))
            ->when($query->shiftId !== null, fn ($b) => $b->where('r.work_shift_id', $query->shiftId))
            ->when($query->startDate !== null, fn ($b) => $b->whereDate('r.work_date', '>=', $query->startDate))
            ->when($query->endDate !== null, fn ($b) => $b->whereDate('r.work_date', '<=', $query->endDate))
            ->when($query->approvalStatus !== null, fn ($b) => $b->where('r.approval_status', $query->approvalStatus))
            ->when($query->calculationStatus !== null, fn ($b) => $b->where('r.calculation_status', $query->calculationStatus))
            ->when($query->locked !== null, fn ($b) => $b->where('r.is_locked', $query->locked))->orderBy('r.id');
        foreach ($builder->cursor() as $x) {
            yield [$this->text($x, 'employee_code'), $this->text($x, 'full_name'), $this->text($x, 'work_date'), $this->nullableText($x, 'name'), $this->nullableText($x, 'check_in_at'), $this->nullableText($x, 'check_out_at'), $this->int($x, 'worked_minutes'), $this->int($x, 'break_minutes'), $this->int($x, 'late_minutes'), $this->int($x, 'early_leave_minutes'), $this->int($x, 'overtime_minutes'), $this->text($x, 'presence_status'), $this->text($x, 'approval_status'), $this->text($x, 'calculation_status'), $this->nullableText($x, 'anomalies'), $this->bool($x, 'is_locked')];
        }
    }

    /** @return Generator<int,list<bool|float|int|string|null>> */
    private function leave(GetWorkforceCsvExportQuery $query): Generator
    {
        $builder = $this->db->table('leave_requests as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('leave_policies as p', 'p.id', '=', 'r.leave_policy_id')->leftJoin('leave_types as t', 't.id', '=', 'r.leave_type_id')->leftJoin('leave_attachments as a', fn (JoinClause $j): JoinClause => $j->on('a.leave_request_id', '=', 'r.id')->whereNull('a.removed_at'))->where('r.company_id', $query->companyId)
            ->when($query->employeeId !== null, fn ($b) => $b->where('r.employee_id', $query->employeeId))
            ->when($query->policyId !== null, fn ($b) => $b->where('r.leave_policy_id', $query->policyId))
            ->when($query->startDate !== null, fn ($b) => $b->whereDate('r.starts_at', '>=', $query->startDate))
            ->when($query->endDate !== null, fn ($b) => $b->whereDate('r.ends_at', '<=', $query->endDate))
            ->when($query->status !== null, fn ($b) => $b->where('r.status', $query->status))
            ->orderBy('r.id')->select('e.employee_code', 'e.full_name', 'p.code as policy', 't.name as type', 'r.starts_at', 'r.ends_at', 'r.requested_minutes', 'r.status', 'r.submitted_at', 'r.approved_at', 'a.status as attachment_status');
        foreach ($builder->cursor() as $x) {
            yield [$this->text($x, 'employee_code'), $this->text($x, 'full_name'), $this->nullableText($x, 'policy'), $this->nullableText($x, 'type'), $this->text($x, 'starts_at'), $this->text($x, 'ends_at'), $this->int($x, 'requested_minutes'), $this->text($x, 'status'), $this->nullableText($x, 'submitted_at'), $this->nullableText($x, 'approved_at'), $this->nullableText($x, 'attachment_status')];
        }
    }

    /** @return Generator<int,list<bool|float|int|string|null>> */
    private function balances(GetWorkforceCsvExportQuery $query): Generator
    {
        $builder = $this->db->table('leave_balance_transactions as l')->join('employees as e', 'e.id', '=', 'l.employee_id')->leftJoin('leave_policies as p', 'p.id', '=', 'l.leave_policy_id')->where('l.company_id', $query->companyId)
            ->when($query->employeeId !== null, fn ($b) => $b->where('l.employee_id', $query->employeeId))
            ->when($query->policyId !== null, fn ($b) => $b->where('l.leave_policy_id', $query->policyId))
            ->when($query->startDate !== null, fn ($b) => $b->whereDate('l.effective_date', '>=', $query->startDate))
            ->when($query->endDate !== null, fn ($b) => $b->whereDate('l.effective_date', '<=', $query->endDate))
            ->groupBy('l.employee_id', 'e.employee_code', 'e.full_name', 'l.leave_policy_id', 'p.code')->selectRaw("e.employee_code,e.full_name,p.code as policy,SUM(CASE WHEN transaction_type='opening' THEN minutes ELSE 0 END) opening,SUM(CASE WHEN transaction_type LIKE 'accrual%' THEN minutes ELSE 0 END) accrued,SUM(CASE WHEN transaction_type='reservation' THEN minutes ELSE 0 END) reserved,SUM(CASE WHEN transaction_type='usage' THEN minutes ELSE 0 END) used,SUM(CASE WHEN transaction_type='carry_forward' THEN minutes ELSE 0 END) carried,SUM(CASE WHEN transaction_type='expiry' THEN minutes ELSE 0 END) expired,SUM(CASE WHEN transaction_type='adjust' THEN minutes ELSE 0 END) adjustment,SUM(minutes) available")->orderBy('l.employee_id');
        foreach ($builder->cursor() as $x) {
            yield [$this->text($x, 'employee_code'), $this->text($x, 'full_name'), $this->nullableText($x, 'policy'), $this->int($x, 'opening'), $this->int($x, 'accrued'), $this->int($x, 'reserved'), $this->int($x, 'used'), $this->int($x, 'carried'), $this->int($x, 'expired'), $this->int($x, 'adjustment'), $this->int($x, 'available')];
        }
    }

    /** @return Generator<int,list<bool|float|int|string|null>> */
    private function overtime(GetWorkforceCsvExportQuery $query): Generator
    {
        $builder = $this->db->table('overtime_requests as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('overtime_policies as p', 'p.id', '=', 'r.overtime_policy_id')->where('r.company_id', $query->companyId)
            ->when($query->employeeId !== null, fn ($b) => $b->where('r.employee_id', $query->employeeId))
            ->when($query->policyId !== null, fn ($b) => $b->where('r.overtime_policy_id', $query->policyId))
            ->when($query->startDate !== null, fn ($b) => $b->whereDate('r.work_date', '>=', $query->startDate))
            ->when($query->endDate !== null, fn ($b) => $b->whereDate('r.work_date', '<=', $query->endDate))
            ->when($query->status !== null, fn ($b) => $b->where('r.status', $query->status))
            ->when($query->calculationStatus !== null, fn ($b) => $b->where('r.calculation_status', $query->calculationStatus))
            ->orderBy('r.id')->select('e.employee_code', 'e.full_name', 'r.work_date', 'p.code as policy', 'r.starts_at', 'r.ends_at', 'r.requested_minutes', 'r.approved_minutes', 'r.actual_minutes', 'r.payable_minutes', 'r.status', 'r.calculation_status');
        foreach ($builder->cursor() as $x) {
            yield [$this->text($x, 'employee_code'), $this->text($x, 'full_name'), $this->text($x, 'work_date'), $this->nullableText($x, 'policy'), $this->text($x, 'starts_at'), $this->text($x, 'ends_at'), $this->int($x, 'requested_minutes'), $this->int($x, 'approved_minutes'), $this->int($x, 'actual_minutes'), $this->int($x, 'payable_minutes'), $this->text($x, 'status'), $this->text($x, 'calculation_status')];
        }
    }

    /** @return Generator<int,list<bool|float|int|string|null>> */
    private function payroll(GetWorkforceCsvExportQuery $query): Generator
    {
        $q = $this->db->table('payroll_employee_inputs as i')->join('employees as e', 'e.id', '=', 'i.employee_id')->join('payroll_input_snapshots as s', 's.id', '=', 'i.payroll_input_snapshot_id')->join('payroll_periods as p', 'p.id', '=', 's.payroll_period_id')->where('i.company_id', $query->companyId)
            ->when($query->snapshotId !== null, fn ($b) => $b->where('s.id', $query->snapshotId))
            ->when($query->employeeId !== null, fn ($b) => $b->where('i.employee_id', $query->employeeId))
            ->when($query->status !== null, fn ($b) => $b->where('s.status', $query->status))
            ->when($query->startDate !== null, fn ($b) => $b->whereDate('p.period_start', '>=', $query->startDate))
            ->when($query->endDate !== null, fn ($b) => $b->whereDate('p.period_end', '<=', $query->endDate))
            ->orderBy('i.id')->select('e.employee_code', 'e.full_name', 'p.code as period', 'i.scheduled_minutes', 'i.worked_minutes', 'i.paid_leave_minutes', 'i.unpaid_leave_minutes', 'i.approved_overtime_minutes', 'i.late_minutes', 'i.early_leave_minutes', 'i.absence_minutes', 's.snapshot_version', 's.status');
        foreach ($q->cursor() as $x) {
            yield [$this->text($x, 'employee_code'), $this->text($x, 'full_name'), $this->text($x, 'period'), $this->int($x, 'scheduled_minutes'), $this->int($x, 'worked_minutes'), $this->int($x, 'paid_leave_minutes'), $this->int($x, 'unpaid_leave_minutes'), $this->int($x, 'approved_overtime_minutes'), $this->int($x, 'late_minutes'), $this->int($x, 'early_leave_minutes'), $this->int($x, 'absence_minutes'), $this->int($x, 'snapshot_version'), $this->text($x, 'status')];
        }
    }

    /** @return Generator<int,list<bool|float|int|string|null>> */
    private function workforce(int $company): Generator
    {
        foreach ([['work_calendars', 'calendar'], ['work_shifts', 'shift']] as [$table,$type]) {
            foreach ($this->db->table($table)->where('company_id', $company)->orderBy('id')->cursor() as $x) {
                yield ['', $type, $this->text($x, 'code'), $this->text($x, 'name'), $this->text($x, 'status'), $this->nullableText($x, 'effective_from'), $this->nullableText($x, 'effective_to'), $this->nullableText($x, 'timezone'), $this->int($x, 'planned_work_minutes') ?: $this->int($x, 'weekly_work_minutes'), null];
            }
        }
    }

    private function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }

    private function nullableText(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }

    private function bool(object $row, string $key): bool
    {
        return ($row->{$key} ?? false) === true || ($row->{$key} ?? 0) === 1;
    }
}
