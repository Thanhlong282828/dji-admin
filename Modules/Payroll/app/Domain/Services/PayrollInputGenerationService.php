<?php

namespace Modules\Payroll\Domain\Services;

use App\Support\Employee\EmployeeBranchResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Events\WorkforcePayrollInputChanged;
use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Models\PayrollPeriod;

final readonly class PayrollInputGenerationService
{
    public function __construct(private PayrollPeriodStateMachine $states, private PayrollOvertimeClassificationService $overtimeClassifier, private EmployeeBranchResolver $branches) {}

    public function generate(int $companyId, int $periodId, int $expectedVersion, int $actorId): PayrollInputSnapshot
    {
        return DB::transaction(function () use ($companyId, $periodId, $expectedVersion, $actorId): PayrollInputSnapshot {
            $period = PayrollPeriod::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($periodId);
            if ($period->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Payroll period đã thay đổi.']);
            }
            $rawStatus = $period->getRawOriginal('status');
            $status = is_string($rawStatus) ? PayrollPeriodStatus::from($rawStatus) : PayrollPeriodStatus::Draft;
            if (! in_array($status, [PayrollPeriodStatus::Collecting, PayrollPeriodStatus::Review], true)) {
                throw ValidationException::withMessages(['status' => 'Chỉ được generate ở collecting/review.']);
            }
            $this->assertReady($companyId, $period);
            $maximumVersion = PayrollInputSnapshot::query()->where('payroll_period_id', $period->id)->max('snapshot_version');
            $version = (is_numeric($maximumVersion) ? (int) $maximumVersion : 0) + 1;
            $snapshot = PayrollInputSnapshot::query()->create(['company_id' => $companyId, 'payroll_period_id' => $period->id, 'snapshot_version' => $version, 'status' => 'draft', 'generated_by' => $actorId, 'generated_at' => now()]);
            $employees = DB::table('employees')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation', 'preboarding'])->whereDate('join_date', '<=', $period->period_end)->get();
            $employeeIds = $employees->pluck('id')->filter(static fn (mixed $id): bool => is_numeric($id))->map(static fn (mixed $id): int => (int) $id)->values()->all();
            $branchIds = $this->branches->resolveMany($companyId, $employeeIds, CarbonImmutable::parse((string) $period->period_end));
            $branchNames = DB::table('branches')->where('company_id', $companyId)->whereIn('id', array_values(array_filter($branchIds, static fn (?int $id): bool => $id !== null)))->pluck('name', 'id');
            $assignments = DB::table('employee_assignments as a')
                ->join('organization_units as o', 'o.id', '=', 'a.organization_unit_id')
                ->leftJoin('job_positions as j', 'j.id', '=', 'a.job_position_id')
                ->leftJoin('cost_centers as c', 'c.id', '=', 'o.cost_center_id')
                ->where('o.company_id', $companyId)->whereIn('a.employee_id', $employeeIds)
                ->where('a.assignment_type', 'primary')->whereDate('a.effective_from', '<=', $period->period_end)
                ->where(fn (Builder $q): Builder => $q->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $period->period_start))
                ->orderBy('a.employee_id')->orderByDesc('a.effective_from')->orderByDesc('a.id')
                ->get(['a.id', 'a.employee_id', 'a.organization_unit_id', 'a.job_position_id', 'a.effective_from', 'a.effective_to', 'o.name as organization_name', 'j.title as position_name', 'c.id as cost_center_id', 'c.company_id as cost_center_company_id', 'c.code as cost_center_code', 'c.name as cost_center_name'])
                ->unique('employee_id')->keyBy('employee_id');
            foreach ($employees as $employee) {
                if (! is_numeric($employee->id ?? null)) {
                    continue;
                }$employeeId = (int) $employee->id;
                $attendance = DB::table('attendance_records')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereBetween('work_date', [$period->period_start, $period->period_end])->where('approval_status', 'approved')->selectRaw('COUNT(*) records,COALESCE(SUM(scheduled_minutes),0) scheduled,COALESCE(SUM(worked_minutes),0) worked,COALESCE(SUM(late_minutes),0) late,COALESCE(SUM(early_leave_minutes),0) early')->first();
                $paidLeave = (int) DB::table('leave_requests')->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')->where('leave_requests.company_id', $companyId)->where('leave_requests.employee_id', $employeeId)->where('leave_requests.status', 'approved')->where('leave_types.is_paid', true)->where('leave_requests.starts_at', '<=', $period->period_end)->where('leave_requests.ends_at', '>=', $period->period_start)->sum('requested_minutes');
                $unpaidLeave = (int) DB::table('leave_requests')->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')->where('leave_requests.company_id', $companyId)->where('leave_requests.employee_id', $employeeId)->where('leave_requests.status', 'approved')->where('leave_types.is_paid', false)->where('leave_requests.starts_at', '<=', $period->period_end)->where('leave_requests.ends_at', '>=', $period->period_start)->sum('requested_minutes');
                $leaveCount = DB::table('leave_requests')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('status', 'approved')->where('starts_at', '<=', $period->period_end)->where('ends_at', '>=', $period->period_start)->count();
                $overtimeSources = DB::table('overtime_requests')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('status', 'approved')->whereBetween('work_date', [$period->period_start, $period->period_end])->orderBy('id')->get();
                $overtime = $weekdayOvertime = $weekendOvertime = $holidayOvertime = 0;
                $overtimeSegments = [];
                $overtimeIssues = [];
                foreach ($overtimeSources as $overtimeSource) {
                    $payable = is_numeric($overtimeSource->payable_minutes ?? null) ? (int) $overtimeSource->payable_minutes : 0;
                    $classification = $this->overtimeClassifier->classify($companyId, $employeeId, $this->string($overtimeSource, 'starts_at'), $this->string($overtimeSource, 'ends_at'), $payable);
                    $overtime += $payable;
                    $weekdayOvertime += $classification->weekdayMinutes;
                    $weekendOvertime += $classification->weekendMinutes;
                    $holidayOvertime += $classification->holidayMinutes;
                    if ($classification->issueCode !== null) {
                        $overtimeIssues[] = $classification->issueCode;
                    }
                    foreach ($classification->segments as $segment) {
                        $overtimeSegments[] = ['source' => $overtimeSource, 'segment' => $segment];
                    }
                }
                $overtimeCount = $overtimeSources->count();
                $assignment = $assignments->get($employeeId);
                if (is_object($assignment) && is_numeric($assignment->cost_center_id ?? null) && (! is_numeric($assignment->cost_center_company_id ?? null) || (int) $assignment->cost_center_company_id !== $companyId)) {
                    throw ValidationException::withMessages(['cost_center' => 'PAYROLL_COST_CENTER_COMPANY_MISMATCH']);
                }
                $contract = DB::table('employment_contracts')->where('employee_id', $employeeId)->whereIn('status', ['active', 'draft'])->whereDate('starts_at', '<=', $period->period_end)->latest('starts_at')->value('id');
                $profile = DB::table('employee_work_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $period->period_end)->latest('effective_from')->first();
                $branchId = $branchIds[$employeeId] ?? null;
                $branchName = $branchId === null ? null : $branchNames->get($branchId);
                $scheduled = is_object($attendance) && is_numeric($attendance->scheduled ?? null) ? (int) $attendance->scheduled : 0;
                $worked = is_object($attendance) && is_numeric($attendance->worked ?? null) ? (int) $attendance->worked : 0;
                $readiness = array_values(array_unique(array_filter([is_numeric($contract) ? null : 'missing_contract', is_object($assignment) ? null : 'missing_assignment', ...$overtimeIssues])));
                $costCenterId = is_object($assignment) && is_numeric($assignment->cost_center_id ?? null) ? (int) $assignment->cost_center_id : null;
                $costCenterCode = is_object($assignment) && is_string($assignment->cost_center_code ?? null) ? $assignment->cost_center_code : null;
                $costCenterName = is_object($assignment) && is_string($assignment->cost_center_name ?? null) ? $assignment->cost_center_name : null;
                $source = [$employeeId, $scheduled, $worked, $paidLeave, $unpaidLeave, $overtime, $leaveCount, $overtimeCount, $costCenterId, $costCenterCode, $costCenterName];
                $employeeInputId = (int) DB::table('payroll_employee_inputs')->insertGetId(['company_id' => $companyId, 'payroll_input_snapshot_id' => $snapshot->id, 'employee_id' => $employeeId, 'employee_number_snapshot' => is_string($employee->employee_code ?? null) ? $employee->employee_code : '', 'employee_name_snapshot' => is_string($employee->full_name ?? null) ? $employee->full_name : '', 'contract_id' => is_numeric($contract) ? (int) $contract : null, 'assignment_id' => is_object($assignment) && is_numeric($assignment->id ?? null) ? (int) $assignment->id : null, 'organization_unit_id' => is_object($assignment) && is_numeric($assignment->organization_unit_id ?? null) ? (int) $assignment->organization_unit_id : null, 'organization_unit_snapshot' => is_object($assignment) && is_string($assignment->organization_name ?? null) ? $assignment->organization_name : null, 'cost_center_id' => $costCenterId, 'cost_center_code_snapshot' => $costCenterCode, 'cost_center_name_snapshot' => $costCenterName, 'job_position_id' => is_object($assignment) && is_numeric($assignment->job_position_id ?? null) ? (int) $assignment->job_position_id : null, 'job_position_snapshot' => is_object($assignment) && is_string($assignment->position_name ?? null) ? $assignment->position_name : null, 'employment_start' => is_object($assignment) && is_string($assignment->effective_from ?? null) ? $assignment->effective_from : null, 'employment_end' => is_object($assignment) && is_string($assignment->effective_to ?? null) ? $assignment->effective_to : null, 'scheduled_minutes' => $scheduled, 'worked_minutes' => $worked, 'paid_leave_minutes' => $paidLeave, 'unpaid_leave_minutes' => $unpaidLeave, 'approved_overtime_minutes' => $overtime, 'weekday_overtime_minutes' => $weekdayOvertime, 'weekend_overtime_minutes' => $weekendOvertime, 'holiday_overtime_minutes' => $holidayOvertime, 'late_minutes' => is_object($attendance) && is_numeric($attendance->late ?? null) ? (int) $attendance->late : 0, 'early_leave_minutes' => is_object($attendance) && is_numeric($attendance->early ?? null) ? (int) $attendance->early : 0, 'absence_minutes' => max(0, $scheduled - $worked - $paidLeave - $unpaidLeave), 'attendance_record_count' => is_object($attendance) && is_numeric($attendance->records ?? null) ? (int) $attendance->records : 0, 'leave_record_count' => $leaveCount, 'overtime_record_count' => $overtimeCount, 'input_status' => $readiness === [] ? 'ready' : 'blocked', 'source_hash' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)), 'fte_basis_points' => is_object($profile) && is_numeric($profile->fte_basis_points ?? null) ? (int) $profile->fte_basis_points : 10000, 'readiness_issues' => json_encode($readiness, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                foreach (DB::table('attendance_records')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereBetween('work_date', [$period->period_start, $period->period_end])->where('approval_status', 'approved')->orderBy('id')->get() as $origin) {
                    $this->captureSource($companyId, (int) $snapshot->id, $employeeInputId, 'attendance', $origin, $this->string($origin, 'work_date'), 'worked_minutes', $this->integer($origin, 'worked_minutes'), null);
                }
                foreach (DB::table('leave_requests')->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')->where('leave_requests.company_id', $companyId)->where('leave_requests.employee_id', $employeeId)->where('leave_requests.status', 'approved')->where('leave_requests.starts_at', '<=', $period->period_end)->where('leave_requests.ends_at', '>=', $period->period_start)->select(['leave_requests.*', 'leave_types.is_paid'])->orderBy('leave_requests.id')->get() as $origin) {
                    $paid = $this->integer($origin, 'is_paid') === 1;
                    $this->captureSource($companyId, (int) $snapshot->id, $employeeInputId, 'leave', $origin, substr($this->string($origin, 'starts_at'), 0, 10), $paid ? 'paid_leave_minutes' : 'unpaid_leave_minutes', $this->integer($origin, 'requested_minutes'), $paid ? 'paid' : 'unpaid');
                }
                foreach ($overtimeSegments as $captured) {
                    $origin = $captured['source'];
                    $segment = $captured['segment'];
                    $checksum = hash('sha256', json_encode([$this->integer($origin, 'id'), $this->string($origin, 'status'), $this->integer($origin, 'payable_minutes'), $this->string($origin, 'starts_at'), $this->string($origin, 'ends_at'), $this->string($origin, 'updated_at')], JSON_THROW_ON_ERROR));
                    DB::table('payroll_input_source_records')->insertOrIgnore(['company_id' => $companyId, 'payroll_input_snapshot_id' => $snapshot->id, 'payroll_employee_input_id' => $employeeInputId, 'source_type' => 'overtime', 'source_id' => $this->integer($origin, 'id'), 'source_date' => $segment['date'], 'source_status_at_capture' => $this->string($origin, 'status'), 'quantity_type' => 'payable_minutes', 'quantity_minutes' => $segment['minutes'], 'classification' => $segment['classification'], 'source_updated_at' => $this->string($origin, 'updated_at') ?: null, 'source_checksum' => $checksum, 'captured_at' => now(), 'metadata' => json_encode(['reason' => $segment['reason']], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('payroll_employee_inputs')->where('id', $employeeInputId)->update(['branch_id' => $branchId, 'branch_snapshot' => is_string($branchName) ? $branchName : null]);
            }$period->status = 'review';
            $period->version++;
            $period->save();
            activity('payroll-input')->performedOn($snapshot)->causedBy($actorId)->event('generated')->withProperties(['company_id' => $companyId, 'period_id' => $periodId, 'snapshot_version' => $version])->log('Payroll input snapshot generated');
            event(new WorkforcePayrollInputChanged($companyId, $periodId, 'generated', 'review', $period->version));

            return $snapshot;
        }, 3);
    }

    public function lock(int $companyId, int $periodId, int $expectedVersion, string $reason, int $actorId): PayrollPeriod
    {
        return DB::transaction(function () use ($companyId, $periodId, $expectedVersion, $reason, $actorId): PayrollPeriod {
            $period = PayrollPeriod::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($periodId);
            if ($period->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Payroll period đã thay đổi.']);
            }
            $rawStatus = $period->getRawOriginal('status');
            $status = is_string($rawStatus) ? PayrollPeriodStatus::from($rawStatus) : PayrollPeriodStatus::Draft;
            $this->states->assertCanTransition($status, PayrollPeriodStatus::Locked);
            $snapshot = PayrollInputSnapshot::query()->where('payroll_period_id', $periodId)->latest('snapshot_version')->lockForUpdate()->firstOrFail();
            $snapshot->update(['status' => 'locked', 'locked_at' => now()]);
            DB::table('attendance_records')->where('company_id', $companyId)->whereBetween('work_date', [$period->period_start, $period->period_end])->update(['is_locked' => true, 'approval_status' => 'locked']);
            $period->update(['status' => 'locked', 'version' => $period->version + 1, 'locked_at' => now(), 'locked_by' => $actorId, 'lock_reason' => $reason]);
            event(new WorkforcePayrollInputChanged($companyId, $periodId, 'locked', 'locked', $period->version));

            return $period->refresh();
        }, 3);
    }

    private function assertReady(int $companyId, PayrollPeriod $period): void
    {
        $pendingAdjustments = DB::table('attendance_adjustment_requests')->join('attendance_records', 'attendance_records.id', '=', 'attendance_adjustment_requests.attendance_record_id')->where('attendance_adjustment_requests.company_id', $companyId)->where('attendance_adjustment_requests.status', 'pending')->whereBetween('attendance_records.work_date', [$period->period_start, $period->period_end])->exists();
        $pendingLeave = DB::table('leave_requests')->where('company_id', $companyId)->where('status', 'submitted')->where('starts_at', '<=', $period->period_end)->where('ends_at', '>=', $period->period_start)->exists();
        $pendingOvertime = DB::table('overtime_requests')->where('company_id', $companyId)->whereIn('status', ['draft', 'submitted'])->whereBetween('work_date', [$period->period_start, $period->period_end])->exists();
        $anomaly = DB::table('attendance_records')->where('company_id', $companyId)->where('calculation_status', 'anomaly')->whereBetween('work_date', [$period->period_start, $period->period_end])->exists();
        if ($pendingAdjustments || $pendingLeave || $pendingOvertime || $anomaly) {
            throw ValidationException::withMessages(['readiness' => 'Payroll inputs chưa sẵn sàng: còn adjustment/leave/overtime pending hoặc attendance anomaly.']);
        }
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }

    private function captureSource(int $companyId, int $snapshotId, int $employeeInputId, string $type, object $origin, string $date, string $quantityType, int $minutes, ?string $classification): void
    {
        $status = $this->string($origin, 'status') ?: $this->string($origin, 'approval_status');
        $updatedAt = $this->string($origin, 'updated_at');
        $payload = [$this->integer($origin, 'id'), $status, $quantityType, $minutes, $date, $updatedAt];
        DB::table('payroll_input_source_records')->insertOrIgnore(['company_id' => $companyId, 'payroll_input_snapshot_id' => $snapshotId, 'payroll_employee_input_id' => $employeeInputId, 'source_type' => $type, 'source_id' => $this->integer($origin, 'id'), 'source_date' => $date, 'source_status_at_capture' => $status, 'quantity_type' => $quantityType, 'quantity_minutes' => $minutes, 'classification' => $classification, 'source_updated_at' => $updatedAt ?: null, 'source_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'captured_at' => now(), 'metadata' => null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
