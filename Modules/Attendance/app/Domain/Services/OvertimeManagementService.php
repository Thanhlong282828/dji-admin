<?php

namespace Modules\Attendance\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Application\Commands\ManageOvertimeCommand;
use Modules\Attendance\Domain\Enums\RoundingRule;
use Modules\Attendance\Domain\Events\WorkforceOvertimeChanged;

final readonly class OvertimeManagementService
{
    public function __construct(private ConnectionInterface $db, private OvertimeEligibilityCalculator $eligibility) {}

    public function execute(ManageOvertimeCommand $command): int
    {
        return $this->db->transaction(fn (): int => match ($command->action) {
            'policy.create', 'policy.update', 'policy.deactivate' => $this->policy($command),
            'assignment.create' => $this->assignment($command),
            'request.create' => $this->createRequest($command),
            'request.submit', 'request.approve', 'request.reject', 'request.cancel' => $this->transition($command),
            'request.reconcile' => $this->reconcile($command),
            default => throw ValidationException::withMessages(['action' => 'Overtime action không hợp lệ.']),
        }, 3);
    }

    private function policy(ManageOvertimeCommand $command): int
    {
        if ($command->action === 'policy.create') {
            if ($this->db->table('overtime_policies')->where('company_id', $command->companyId)->where('code', strtoupper($this->dataString($command, 'code')))->exists()) {
                throw ValidationException::withMessages(['code' => 'Mã overtime policy đã tồn tại.']);
            }
            $id = (int) $this->db->table('overtime_policies')->insertGetId($this->policyData($command) + ['company_id' => $command->companyId, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $policy = $this->db->table('overtime_policies')->where('company_id', $command->companyId)->where('id', $command->subjectId)->lockForUpdate()->first();
            abort_if($policy === null, 404);
            $this->assertVersion($policy, $command->expectedVersion);
            $id = $this->int($policy, 'id');
            $data = $command->action === 'policy.deactivate' ? ['status' => 'inactive'] : $this->policyData($command);
            $this->db->table('overtime_policies')->where('id', $id)->update($data + ['version' => $command->expectedVersion + 1, 'updated_at' => now()]);
        }
        $this->log($command, $id, null, null);

        return $id;
    }

    /** @return array<string, bool|int|string|null> */
    private function policyData(ManageOvertimeCommand $command): array
    {
        $from = $this->dataString($command, 'effective_from');
        $to = $this->dataNullableString($command, 'effective_to');
        if ($to !== null && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => 'Ngày kết thúc không hợp lệ.']);
        }
        $daily = $this->dataInt($command, 'daily_limit_minutes');
        $weekly = $this->dataInt($command, 'weekly_limit_minutes');
        if ($weekly < $daily) {
            throw ValidationException::withMessages(['weekly_limit_minutes' => 'Giới hạn tuần không được thấp hơn giới hạn ngày.']);
        }
        $days = $this->dataStringList($command, 'eligible_day_types');

        return ['code' => strtoupper($this->dataString($command, 'code')), 'name' => $this->dataString($command, 'name'), 'minimum_minutes' => $this->dataInt($command, 'minimum_minutes'), 'rounding_rule' => $this->dataString($command, 'rounding_rule'), 'daily_limit_minutes' => $daily, 'weekly_limit_minutes' => $weekly, 'requires_preapproval' => $this->dataBool($command, 'requires_preapproval'), 'eligible_day_types' => json_encode($days), 'status' => $this->dataString($command, 'status'), 'effective_from' => $from, 'effective_to' => $to];
    }

    private function assignment(ManageOvertimeCommand $command): int
    {
        $employeeId = $this->dataInt($command, 'employee_id');
        $policyId = $this->dataInt($command, 'overtime_policy_id');
        $this->employee($command->companyId, $employeeId);
        $this->policyObject($command->companyId, $policyId);
        $existing = $this->db->table('employee_overtime_policy_assignments')->where('company_id', $command->companyId)->where('idempotency_key', $command->idempotencyKey)->value('id');
        if (is_numeric($existing)) {
            return (int) $existing;
        }
        $from = $this->dataString($command, 'effective_from');
        $effectiveTo = $this->dataNullableString($command, 'effective_to');
        $to = $effectiveTo ?? '9999-12-31';
        $overlap = $this->db->table('employee_overtime_policy_assignments')->where('company_id', $command->companyId)->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $to)->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->lockForUpdate()->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['assignment' => 'Nhân viên đã có overtime policy trong khoảng hiệu lực này.']);
        }
        $id = (int) $this->db->table('employee_overtime_policy_assignments')->insertGetId(['company_id' => $command->companyId, 'employee_id' => $employeeId, 'overtime_policy_id' => $policyId, 'effective_from' => $from, 'effective_to' => $effectiveTo, 'version' => 1, 'idempotency_key' => $command->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
        $this->log($command, $id, null, null);

        return $id;
    }

    private function createRequest(ManageOvertimeCommand $command): int
    {
        $existing = $this->db->table('overtime_requests')->where('company_id', $command->companyId)->where('idempotency_key', $command->idempotencyKey)->value('id');
        if (is_numeric($existing)) {
            return (int) $existing;
        }
        $employeeId = $this->dataInt($command, 'employee_id');
        $this->employee($command->companyId, $employeeId);
        $this->db->table('employees')->where('id', $employeeId)->lockForUpdate()->value('id');
        $workDate = $this->dataString($command, 'work_date');
        $this->assertPeriodUnlocked($command->companyId, $workDate);
        $policy = $this->activePolicy($command->companyId, $employeeId, $workDate);
        $timezone = $this->employeeTimezone($command->companyId, $employeeId, $workDate);
        $start = CarbonImmutable::parse($this->dataString($command, 'starts_at'), $timezone);
        $end = CarbonImmutable::parse($this->dataString($command, 'ends_at'), $timezone);
        $minutes = (int) $start->diffInMinutes($end, false);
        if ($minutes <= 0 || $start->toDateString() !== $workDate) {
            throw ValidationException::withMessages(['ends_at' => 'Khoảng overtime hoặc work date không hợp lệ.']);
        }
        $minutes = RoundingRule::from($this->text($policy, 'rounding_rule'))->apply($minutes);
        $daily = $this->int($policy, 'daily_limit_minutes');
        if ($minutes < $this->int($policy, 'minimum_minutes') || $minutes > $daily) {
            throw ValidationException::withMessages(['requested_minutes' => 'Thời lượng không đạt minimum hoặc vượt daily limit.']);
        }
        $weekStart = CarbonImmutable::parse($workDate)->startOfWeek()->toDateString();
        $weekEnd = CarbonImmutable::parse($workDate)->endOfWeek()->toDateString();
        $weekly = (int) $this->db->table('overtime_requests')->where('company_id', $command->companyId)->where('employee_id', $employeeId)->whereIn('status', ['draft', 'submitted', 'approved'])->whereBetween('work_date', [$weekStart, $weekEnd])->sum('requested_minutes');
        if ($weekly + $minutes > $this->int($policy, 'weekly_limit_minutes')) {
            throw ValidationException::withMessages(['requested_minutes' => 'Vượt giới hạn overtime tuần.']);
        }
        $overlap = $this->db->table('overtime_requests')->where('company_id', $command->companyId)->where('employee_id', $employeeId)->whereNotIn('status', ['cancelled', 'rejected'])->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Khoảng overtime bị trùng.']);
        }
        $id = (int) $this->db->table('overtime_requests')->insertGetId(['company_id' => $command->companyId, 'employee_id' => $employeeId, 'overtime_policy_id' => $this->int($policy, 'id'), 'work_date' => $workDate, 'starts_at' => $start->utc(), 'ends_at' => $end->utc(), 'requested_minutes' => $minutes, 'approved_minutes' => 0, 'actual_minutes' => 0, 'payable_minutes' => 0, 'calculation_status' => 'pending', 'calculation_version' => 1, 'reason' => $this->dataString($command, 'reason'), 'status' => 'draft', 'version' => 1, 'idempotency_key' => $command->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
        $this->history($command, $id, null, 'draft', 1, null, ['requested_minutes' => $minutes]);
        $this->log($command, $id, null, 'draft');
        $this->emit($command->companyId, $id, $employeeId, 'request.created', 'draft', 1);

        return $id;
    }

    private function transition(ManageOvertimeCommand $command): int
    {
        $preview = $this->db->table('overtime_requests')->where('company_id', $command->companyId)->where('id', (int) $command->subjectId)->first();
        abort_if($preview === null, 404);
        $this->lockPeriodForDate($command->companyId, $this->text($preview, 'work_date'));
        $request = $this->request($command->companyId, (int) $command->subjectId);
        $this->assertVersion($request, $command->expectedVersion);
        $from = $this->text($request, 'status');
        $target = match ($command->action) {
            'request.submit' => 'submitted', 'request.approve' => 'approved', 'request.reject' => 'rejected', 'request.cancel' => 'cancelled', default => ''
        };
        $allowed = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected', 'cancelled']];
        if (! in_array($target, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển {$from} sang {$target}."]);
        }
        if ($target === 'submitted') {
            $employeeId = $this->int($request, 'employee_id');
            $this->db->table('employees')->where('id', $employeeId)->lockForUpdate()->value('id');
            $overlap = $this->db->table('overtime_requests')->where('company_id', $command->companyId)->where('employee_id', $employeeId)->where('id', '<>', (int) $command->subjectId)->whereIn('status', ['submitted', 'approved'])->where('starts_at', '<', $this->text($request, 'ends_at'))->where('ends_at', '>', $this->text($request, 'starts_at'))->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => 'Khoảng overtime bị trùng với request đã submit/approve.']);
            }
        }
        $comment = $command->data['comment'] ?? null;
        if ($target === 'rejected' && (! is_string($comment) || trim($comment) === '')) {
            throw ValidationException::withMessages(['comment' => 'Lý do từ chối là bắt buộc.']);
        }
        if (in_array($target, ['approved', 'rejected'], true) && $this->employeeUserId($this->int($request, 'employee_id')) === $command->actorId) {
            throw ValidationException::withMessages(['approval' => 'Không được tự duyệt overtime.']);
        }
        $version = $command->expectedVersion + 1;
        $approved = $target === 'approved' ? $this->int($request, 'requested_minutes') : $this->int($request, 'approved_minutes');
        $this->db->table('overtime_requests')->where('id', $command->subjectId)->update(['status' => $target, 'approved_minutes' => $approved, 'approver_id' => $target === 'submitted' ? $this->managerUserId($this->int($request, 'employee_id')) : ($request->approver_id ?? null), 'reviewed_by' => in_array($target, ['approved', 'rejected'], true) ? $command->actorId : null, 'reviewed_at' => in_array($target, ['approved', 'rejected'], true) ? now() : null, 'review_comment' => is_string($comment) ? $comment : null, 'version' => $version, 'updated_at' => now()]);
        $this->history($command, (int) $command->subjectId, $from, $target, $version, is_string($comment) ? $comment : null, ['approved_minutes' => $approved]);
        $this->log($command, (int) $command->subjectId, $from, $target);
        $this->emit($command->companyId, (int) $command->subjectId, $this->int($request, 'employee_id'), $command->action, $target, $version);

        return (int) $command->subjectId;
    }

    private function reconcile(ManageOvertimeCommand $command): int
    {
        $preview = $this->db->table('overtime_requests')->where('company_id', $command->companyId)->where('id', (int) $command->subjectId)->first();
        abort_if($preview === null, 404);
        $this->lockPeriodForDate($command->companyId, $this->text($preview, 'work_date'));
        $request = $this->request($command->companyId, (int) $command->subjectId);
        $this->assertVersion($request, $command->expectedVersion);
        if ($this->text($request, 'status') !== 'approved') {
            throw ValidationException::withMessages(['status' => 'Chỉ reconcile overtime đã duyệt.']);
        }
        $attendance = $this->db->table('attendance_records')->where('company_id', $command->companyId)->where('employee_id', $this->int($request, 'employee_id'))->where('work_date', $this->text($request, 'work_date'))->where('approval_status', 'approved')->lockForUpdate()->first();
        if ($attendance === null) {
            throw ValidationException::withMessages(['attendance' => 'Chưa có attendance approved cho ngày overtime.']);
        }
        $policy = $this->policyObject($command->companyId, $this->int($request, 'overtime_policy_id'));
        $actual = $this->int($attendance, 'overtime_minutes');
        $eligible = RoundingRule::from($this->text($policy, 'rounding_rule'))->apply($actual);
        $payable = $this->eligibility->payableMinutes($this->int($request, 'approved_minutes'), $eligible, $this->int($policy, 'minimum_minutes'), $this->int($policy, 'daily_limit_minutes'));
        $version = $command->expectedVersion + 1;
        $calculationVersion = $this->int($request, 'calculation_version') + 1;
        $this->db->table('overtime_requests')->where('id', $command->subjectId)->update(['actual_minutes' => $actual, 'payable_minutes' => $payable, 'calculation_status' => 'reconciled', 'calculation_version' => $calculationVersion, 'version' => $version, 'updated_at' => now()]);
        $this->history($command, (int) $command->subjectId, 'approved', 'approved', $version, null, ['actual_minutes' => $actual, 'payable_minutes' => $payable, 'calculation_version' => $calculationVersion]);
        $this->log($command, (int) $command->subjectId, 'approved', 'approved');
        $this->emit($command->companyId, (int) $command->subjectId, $this->int($request, 'employee_id'), 'request.reconciled', 'approved', $version);

        return (int) $command->subjectId;
    }

    private function activePolicy(int $companyId, int $employeeId, string $date): object
    {
        $rows = $this->db->table('employee_overtime_policy_assignments as a')->join('overtime_policies as p', 'p.id', '=', 'a.overtime_policy_id')->where('a.company_id', $companyId)->where('a.employee_id', $employeeId)->where('p.status', 'active')->whereDate('a.effective_from', '<=', $date)->where(fn (Builder $query): Builder => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $date))->whereDate('p.effective_from', '<=', $date)->where(fn (Builder $query): Builder => $query->whereNull('p.effective_to')->orWhereDate('p.effective_to', '>=', $date))->select('p.*')->lockForUpdate()->get();
        if ($rows->count() !== 1) {
            throw ValidationException::withMessages(['overtime_policy_id' => 'Phải có đúng một overtime policy active tại work date.']);
        }

        return $rows->firstOrFail();
    }

    private function request(int $companyId, int $id): object
    {
        $row = $this->db->table('overtime_requests')->where('company_id', $companyId)->where('id', $id)->lockForUpdate()->first();
        abort_if($row === null, 404);

        return $row;
    }

    private function policyObject(int $companyId, int $id): object
    {
        $row = $this->db->table('overtime_policies')->where('company_id', $companyId)->where('id', $id)->first();
        abort_if($row === null, 404);

        return $row;
    }

    private function employee(int $companyId, int $id): void
    {
        abort_unless($this->db->table('employees')->where('company_id', $companyId)->where('id', $id)->whereNull('deleted_at')->exists(), 404);
    }

    private function employeeTimezone(int $companyId, int $employeeId, string $date): string
    {
        $value = $this->db->table('employee_work_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $date)->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->latest('effective_from')->value('timezone');

        return is_string($value) ? $value : 'Asia/Ho_Chi_Minh';
    }

    private function employeeUserId(int $employeeId): int
    {
        $value = $this->db->table('employees')->where('id', $employeeId)->value('user_id');

        return is_numeric($value) ? (int) $value : 0;
    }

    private function managerUserId(int $employeeId): ?int
    {
        $value = $this->db->table('employees as e')->leftJoin('employees as m', 'm.id', '=', 'e.manager_id')->where('e.id', $employeeId)->value('m.user_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function assertPeriodUnlocked(int $companyId, string $date): void
    {
        if ($this->db->table('payroll_periods')->where('company_id', $companyId)->where('status', 'locked')->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['work_date' => 'Payroll period đã khóa.']);
        }
    }

    private function lockPeriodForDate(int $companyId, string $date): void
    {
        $period = $this->db->table('payroll_periods')->where('company_id', $companyId)->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)->lockForUpdate()->first();
        if ($period !== null && $this->text($period, 'status') === 'locked') {
            throw ValidationException::withMessages(['work_date' => 'Payroll period đã khóa.']);
        }
    }

    private function assertVersion(object $row, int $version): void
    {
        if ($this->int($row, 'version') !== $version) {
            throw ValidationException::withMessages(['version' => 'Dữ liệu overtime đã thay đổi.']);
        }
    }

    /** @param array<string,int|string> $snapshot */
    private function history(ManageOvertimeCommand $command, int $id, ?string $from, string $to, int $version, ?string $comment, array $snapshot): void
    {
        $this->db->table('overtime_request_histories')->insert(['company_id' => $command->companyId, 'overtime_request_id' => $id, 'action' => $command->action, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $command->actorId, 'comment' => $comment, 'snapshot' => json_encode($snapshot), 'request_version' => $version, 'created_at' => now()]);
    }

    private function log(ManageOvertimeCommand $command, int $id, ?string $from, ?string $to): void
    {
        activity('overtime')->causedBy($command->actorId)->event($command->action)->withProperties(['company_id' => $command->companyId, 'subject_id' => $id, 'from' => $from, 'to' => $to])->log('Overtime '.$command->action);
    }

    private function emit(int $companyId, int $id, int $employeeId, string $action, string $status, int $version): void
    {
        event(new WorkforceOvertimeChanged($companyId, $id, $employeeId, $action, $status, $version));
    }

    private function int(object $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }

    private function dataString(ManageOvertimeCommand $command, string $key): string
    {
        $value = $command->data[$key] ?? null;

        if (! is_string($value)) {
            throw ValidationException::withMessages([$key => 'Giá trị chuỗi không hợp lệ.']);
        }

        return $value;
    }

    private function dataNullableString(ManageOvertimeCommand $command, string $key): ?string
    {
        $value = $command->data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function dataInt(ManageOvertimeCommand $command, string $key): int
    {
        $value = $command->data[$key] ?? null;

        if (! is_int($value)) {
            throw ValidationException::withMessages([$key => 'Giá trị số nguyên không hợp lệ.']);
        }

        return $value;
    }

    private function dataBool(ManageOvertimeCommand $command, string $key): bool
    {
        $value = $command->data[$key] ?? null;

        return is_bool($value) ? $value : false;
    }

    /** @return list<string> */
    private function dataStringList(ManageOvertimeCommand $command, string $key): array
    {
        $value = $command->data[$key] ?? null;

        if (! is_array($value)) {
            throw ValidationException::withMessages([$key => 'Danh sách không hợp lệ.']);
        }

        return $value;
    }
}
