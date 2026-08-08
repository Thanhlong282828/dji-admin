<?php

namespace Modules\Attendance\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\Application\Commands\ProcessAttendanceWorkflowCommand;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;
use Modules\Attendance\Domain\Repositories\AttendanceWorkflowRepositoryInterface;

final readonly class AttendanceWorkflowService
{
    public function __construct(private ConnectionInterface $db, private AttendanceCalculator $calculator) {}

    public function execute(ProcessAttendanceWorkflowCommand $c): int
    {
        return $this->db->transaction(function () use ($c): int {
            if (str_starts_with($c->action, 'adjustment.')) {
                return $this->adjustment($c);
            }
            if (str_starts_with($c->action, 'attendance.')) {
                return $this->attendance($c);
            }
            if ($c->action === 'period.lock') {
                return $this->lockPeriod($c);
            }
            throw ValidationException::withMessages(['action' => 'Workflow action không hợp lệ.']);
        }, 3);
    }

    private function adjustment(ProcessAttendanceWorkflowCommand $c): int
    {
        if ($c->action === 'adjustment.create') {
            $record = $this->record($c->companyId, $c->subjectId);
            $this->assertUnlocked($record);
            $key = $c->data['idempotency_key'] ?? null;
            if (is_string($key)) {
                $existing = $this->db->table('attendance_adjustment_requests')->where('company_id', $c->companyId)->where('idempotency_key', $key)->value('id');
                if (is_numeric($existing)) {
                    return (int) $existing;
                }
            }
            $this->validateTimes($c->data);
            $pending = $this->db->table('attendance_adjustment_requests')->where('attendance_record_id', $c->subjectId)->whereIn('status', ['submitted', 'pending'])->exists();
            if ($pending) {
                throw ValidationException::withMessages(['adjustment' => 'Record đã có adjustment chờ xử lý.']);
            }
            $id = (int) $this->db->table('attendance_adjustment_requests')->insertGetId(['company_id' => $c->companyId, 'attendance_record_id' => $c->subjectId, 'employee_id' => $this->int($record, 'employee_id'), 'requested_by' => $c->actorId, 'idempotency_key' => $key, 'requested_check_in_at' => $c->data['check_in_at'] ?? null, 'requested_check_out_at' => $c->data['check_out_at'] ?? null, 'requested_break_minutes' => $c->data['break_minutes'] ?? 0, 'requested_source' => 'manual_adjustment', 'reason' => $c->data['reason'] ?? '', 'expected_record_version' => $c->expectedVersion, 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->adjustmentHistory($c->companyId, $id, 'created', null, ['status' => 'draft'], $c->actorId, null);
            $this->emit($c->companyId, $id, $this->int($record, 'employee_id'), 'adjustment.created');

            return $id;
        }
        $a = $this->db->table('attendance_adjustment_requests')->where('company_id', $c->companyId)->where('id', $c->subjectId)->lockForUpdate()->first();
        abort_if($a === null, 404);
        if ($this->int($a, 'version') !== $c->expectedVersion) {
            $this->stale();
        } $record = $this->record($c->companyId, $this->int($a, 'attendance_record_id'));
        $this->assertUnlocked($record);
        $from = $this->text($a, 'status');
        $target = match ($c->action) {
            'adjustment.submit' => 'submitted','adjustment.approve' => 'approved','adjustment.reject' => 'rejected','adjustment.cancel' => 'cancelled',default => throw ValidationException::withMessages(['action' => 'Action adjustment không hợp lệ.'])
        };
        $allowed = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected', 'cancelled']];
        if (! in_array($target, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển {$from} sang {$target}."]);
        }
        if ($target === 'approved') {
            if ($this->int($a, 'requested_by') === $c->actorId) {
                throw ValidationException::withMessages(['approval' => 'Không được tự duyệt adjustment.']);
            }
            if ($this->int($record, 'version') !== $this->int($a, 'expected_record_version')) {
                $this->stale();
            }
            $checkIn = $this->nullableText($a, 'requested_check_in_at');
            $checkOut = $this->nullableText($a, 'requested_check_out_at');
            $break = $this->int($a, 'requested_break_minutes');
            $this->validateTimes(['check_in_at' => $checkIn, 'check_out_at' => $checkOut, 'break_minutes' => $break]);
            $worked = $checkIn !== null && $checkOut !== null ? max(0, (int) CarbonImmutable::parse($checkIn)->diffInMinutes(CarbonImmutable::parse($checkOut)) - $break) : 0;
            $raw = $record->anomalies ?? null;
            $anomalies = is_string($raw) ? json_decode($raw, true) : [];
            $remaining = is_array($anomalies) ? array_values(array_filter($anomalies, static fn ($x): bool => is_string($x) && ! str_contains($x, 'missing'))) : [];
            $this->db->table('attendance_records')->where('id', $this->int($record, 'id'))->update(['check_in_at' => $checkIn, 'check_out_at' => $checkOut, 'break_minutes' => $break, 'worked_minutes' => $worked, 'worked_hours' => number_format($worked / 60, 2, '.', ''), 'anomalies' => json_encode($remaining), 'calculation_status' => $remaining === [] ? 'calculated' : 'anomaly', 'calculation_version' => $this->int($record, 'calculation_version') + 1, 'version' => $this->int($record, 'version') + 1, 'adjustment' => json_encode(['adjustment_id' => $c->subjectId]), 'updated_at' => now()]);
        }
        $comment = $c->data['comment'] ?? null;
        if ($target === 'rejected' && (! is_string($comment) || trim($comment) === '')) {
            throw ValidationException::withMessages(['comment' => 'Lý do từ chối là bắt buộc.']);
        }
        $before = ['status' => $from, 'version' => $this->int($a, 'version')];
        $after = ['status' => $target, 'version' => $this->int($a, 'version') + 1];
        $this->db->table('attendance_adjustment_requests')->where('id', $c->subjectId)->update(['status' => $target, 'version' => $after['version'], 'reviewed_by' => in_array($target, ['approved', 'rejected'], true) ? $c->actorId : null, 'reviewed_at' => in_array($target, ['approved', 'rejected'], true) ? now() : null, 'review_comment' => $comment, 'before_snapshot' => json_encode($before), 'after_snapshot' => json_encode($after), 'updated_at' => now()]);
        $this->adjustmentHistory($c->companyId, $c->subjectId, $target, $before, $after, $c->actorId, is_string($comment) ? $comment : null);
        activity('attendance')->causedBy($c->actorId)->event('adjustment_'.$target)->withProperties(['company_id' => $c->companyId, 'adjustment_id' => $c->subjectId, 'from' => $from, 'to' => $target])->log('Attendance adjustment '.$target);
        $this->emit($c->companyId, $c->subjectId, $this->int($record, 'employee_id'), 'adjustment.'.$target);

        return $c->subjectId;
    }

    private function attendance(ProcessAttendanceWorkflowCommand $c): int
    {
        $r = $this->record($c->companyId, $c->subjectId);
        $this->assertUnlocked($r);
        if ($this->int($r, 'version') !== $c->expectedVersion) {
            $this->stale();
        }
        $from = $this->text($r, 'approval_status');
        $target = match ($c->action) {
            'attendance.submit' => 'submitted','attendance.approve' => 'approved','attendance.reject' => 'rejected',default => ''
        };
        $allowed = ['draft' => ['submitted'], 'rejected' => ['submitted'], 'submitted' => ['approved', 'rejected']];
        if (! in_array($target, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển {$from} sang {$target}."]);
        }
        if ($target === 'submitted' && $this->text($r, 'calculation_status') !== 'calculated') {
            throw ValidationException::withMessages(['calculation' => 'Attendance chưa được tính hợp lệ.']);
        }
        if ($this->db->table('attendance_adjustment_requests')->where('attendance_record_id', $c->subjectId)->whereIn('status', ['submitted', 'pending'])->exists()) {
            throw ValidationException::withMessages(['adjustment' => 'Còn adjustment chờ xử lý.']);
        }
        if ($target === 'approved' && $this->employeeUserId($this->int($r, 'employee_id')) === $c->actorId) {
            throw ValidationException::withMessages(['approval' => 'Không được tự duyệt attendance.']);
        }
        $comment = $c->data['comment'] ?? null;
        if ($target === 'rejected' && (! is_string($comment) || trim($comment) === '')) {
            throw ValidationException::withMessages(['comment' => 'Lý do từ chối là bắt buộc.']);
        }
        $version = $this->int($r, 'version') + 1;
        $this->db->table('attendance_records')->where('id', $c->subjectId)->update(['approval_status' => $target, 'version' => $version, 'updated_at' => now()]);
        $this->db->table('attendance_approval_histories')->insert(['company_id' => $c->companyId, 'attendance_record_id' => $c->subjectId, 'action' => $target, 'from_status' => $from, 'to_status' => $target, 'actor_id' => $c->actorId, 'comment' => $comment, 'record_version' => $version, 'created_at' => now()]);
        activity('attendance')->causedBy($c->actorId)->event($target)->withProperties(['company_id' => $c->companyId, 'record_id' => $c->subjectId, 'from' => $from, 'to' => $target])->log('Attendance '.$target);
        $this->emit($c->companyId, $c->subjectId, $this->int($r, 'employee_id'), 'attendance.'.$target);

        return $c->subjectId;
    }

    private function lockPeriod(ProcessAttendanceWorkflowCommand $c): int
    {
        $p = $this->db->table('payroll_periods')->where('company_id', $c->companyId)->where('id', $c->subjectId)->lockForUpdate()->first();
        abort_if($p === null, 404);
        if ($this->int($p, 'version') !== $c->expectedVersion) {
            $this->stale();
        } if ($this->text($p, 'status') === 'locked') {
            return $c->subjectId;
        } if (! in_array($this->text($p, 'status'), ['review', 'collecting'], true)) {
            throw ValidationException::withMessages(['status' => 'Payroll period chưa sẵn sàng lock.']);
        }
        $this->db->table('attendance_records')->where('company_id', $c->companyId)->whereBetween('work_date', [$this->text($p, 'period_start'), $this->text($p, 'period_end')])->orderBy('id')->lockForUpdate()->get(['id']);
        $readiness = app(AttendanceWorkflowRepositoryInterface::class)->readinessFresh($c->companyId, $c->subjectId);
        if (! $readiness->canLock) {
            throw ValidationException::withMessages(['readiness' => implode(' ', $readiness->blockingIssues)]);
        }
        $this->db->table('attendance_records')->where('company_id', $c->companyId)->whereBetween('work_date', [$this->text($p, 'period_start'), $this->text($p, 'period_end')])->lockForUpdate()->update(['is_locked' => true, 'locked_at' => now(), 'locked_by' => $c->actorId, 'payroll_period_id' => $c->subjectId, 'version' => $this->db->raw('version + 1'), 'updated_at' => now()]);
        $this->db->table('payroll_periods')->where('id', $c->subjectId)->update(['status' => 'locked', 'locked_at' => now(), 'locked_by' => $c->actorId, 'lock_reason' => $c->data['reason'] ?? null, 'version' => $this->int($p, 'version') + 1, 'updated_at' => now()]);
        activity('attendance')->causedBy($c->actorId)->event('period_locked')->withProperties(['company_id' => $c->companyId, 'payroll_period_id' => $c->subjectId])->log('Attendance period locked');
        $this->emit($c->companyId, $c->subjectId, 0, 'period.locked');

        return $c->subjectId;
    }

    private function record(int $companyId, int $id): object
    {
        $r = $this->db->table('attendance_records')->where('company_id', $companyId)->where('id', $id)->lockForUpdate()->first();
        abort_if($r === null, 404);

        return $r;
    }

    private function assertUnlocked(object $r): void
    {
        if ((bool) ($r->is_locked ?? false)) {
            throw ValidationException::withMessages(['record' => 'Attendance record đã khóa.']);
        }
    }

    /** @param array<string,int|string|null> $data */
    private function validateTimes(array $data): void
    {
        $in = $data['check_in_at'] ?? null;
        $out = $data['check_out_at'] ?? null;
        $break = (int) ($data['break_minutes'] ?? 0);
        if ($break < 0) {
            throw ValidationException::withMessages(['break_minutes' => 'Break không được âm.']);
        }if (is_string($in) && is_string($out)) {
            $minutes = (int) CarbonImmutable::parse($in)->diffInMinutes(CarbonImmutable::parse($out), false);
            if ($minutes <= 0) {
                throw ValidationException::withMessages(['check_out_at' => 'Check-out phải sau check-in.']);
            }if ($break > $minutes) {
                throw ValidationException::withMessages(['break_minutes' => 'Break vượt quá session.']);
            }
        }
    }

    /**
     * @param  array<string,int|string|null>|null  $before
     * @param  array<string,int|string|null>  $after
     */
    private function adjustmentHistory(int $companyId, int $id, string $action, ?array $before, array $after, int $actor, ?string $comment): void
    {
        $this->db->table('attendance_adjustment_histories')->insert(['company_id' => $companyId, 'attendance_adjustment_request_id' => $id, 'action' => $action, 'before_snapshot' => $before === null ? null : json_encode($before), 'after_snapshot' => json_encode($after), 'actor_id' => $actor, 'comment' => $comment, 'created_at' => now()]);
    }

    private function employeeUserId(int $employeeId): int
    {
        $v = $this->db->table('employees')->where('id', $employeeId)->value('user_id');

        return is_numeric($v) ? (int) $v : 0;
    }

    private function emit(int $companyId, int $id, int $employeeId, string $action): void
    {
        event(new WorkforceAttendanceChanged($companyId, $id, $employeeId, $action));
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['version' => 'Dữ liệu đã thay đổi, vui lòng tải lại.']);
    }

    private function int(object $o, string $p): int
    {
        $v = $o->{$p} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function text(object $o, string $p): string
    {
        $v = $o->{$p} ?? null;

        return is_string($v) ? $v : '';
    }

    private function nullableText(object $o, string $p): ?string
    {
        $v = $o->{$p} ?? null;

        return is_string($v) ? $v : null;
    }
}
