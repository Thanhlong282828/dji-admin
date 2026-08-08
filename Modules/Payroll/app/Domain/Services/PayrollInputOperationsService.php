<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Events\WorkforcePayrollInputChanged;
use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Models\PayrollRun;

final readonly class PayrollInputOperationsService
{
    public function __construct(private PayrollInputGenerationService $generator, private PayrollRunService $runs) {}

    public function capture(int $companyId, int $periodId, int $actorId, string $idempotencyKey, ?string $note): PayrollInputSnapshot
    {
        $existing = PayrollInputSnapshot::query()->where('company_id', $companyId)->where('payroll_period_id', $periodId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof PayrollInputSnapshot) {
            return $existing;
        }
        $period = DB::table('payroll_periods')->where('company_id', $companyId)->where('id', $periodId)->first();
        if (! is_object($period)) {
            abort(404);
        }
        $version = is_numeric($period->version ?? null) ? (int) $period->version : 0;
        $snapshot = $this->generator->generate($companyId, $periodId, $version, $actorId);

        return DB::transaction(function () use ($snapshot, $companyId, $periodId, $actorId, $idempotencyKey, $note): PayrollInputSnapshot {
            $snapshot = PayrollInputSnapshot::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($snapshot->id);
            $snapshot->update(['idempotency_key' => $idempotencyKey, 'note' => $note]);
            $this->validatePersisted($snapshot, $actorId, $idempotencyKey);
            PayrollInputSnapshot::query()->where('company_id', $companyId)->where('payroll_period_id', $periodId)->where('id', '<>', $snapshot->id)->whereNotIn('status', ['superseded', 'locked'])->update(['status' => 'superseded', 'superseded_at' => now(), 'superseded_by_snapshot_id' => $snapshot->id]);
            $this->history($snapshot, 'captured', 'draft', (string) $snapshot->status, $actorId, $idempotencyKey);
            event(new WorkforcePayrollInputChanged($companyId, $snapshot->id, 'captured', (string) $snapshot->status, $snapshot->snapshot_version));

            return $snapshot->refresh();
        }, 3);
    }

    public function validate(int $companyId, int $snapshotId, int $actorId, string $idempotencyKey): PayrollInputSnapshot
    {
        return DB::transaction(function () use ($companyId, $snapshotId, $actorId, $idempotencyKey): PayrollInputSnapshot {
            $snapshot = PayrollInputSnapshot::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($snapshotId);
            $from = (string) $snapshot->status;
            $this->validatePersisted($snapshot, $actorId, $idempotencyKey);
            $this->history($snapshot, 'validated', $from, (string) $snapshot->status, $actorId, $idempotencyKey);
            event(new WorkforcePayrollInputChanged($companyId, $snapshot->id, 'validated', (string) $snapshot->status, $snapshot->snapshot_version));

            return $snapshot->refresh();
        }, 3);
    }

    public function createRun(int $companyId, int $snapshotId, int $actorId, string $idempotencyKey): PayrollRun
    {
        $snapshot = PayrollInputSnapshot::query()->where('company_id', $companyId)->findOrFail($snapshotId);
        if ($snapshot->status !== 'ready') {
            throw ValidationException::withMessages(['snapshot' => 'Chỉ snapshot READY mới có thể tạo Payroll Run.']);
        }
        $period = DB::table('payroll_periods')->where('company_id', $companyId)->where('id', $snapshot->payroll_period_id)->first();
        if (! is_object($period)) {
            abort(404);
        }
        $periodCode = is_string($period->code ?? null) ? $period->code : (string) $snapshot->payroll_period_id;
        $currency = DB::table('companies')->where('id', $companyId)->value('currency_code');
        $run = $this->runs->create($companyId, $snapshot->payroll_period_id, $snapshot->id, 'RUN-'.$periodCode.'-V'.$snapshot->snapshot_version, is_string($currency) ? $currency : 'VND', $idempotencyKey, $actorId);
        $this->history($snapshot, 'payroll_run_created', 'ready', 'ready', $actorId, $idempotencyKey, ['run_id' => $run->id]);
        event(new WorkforcePayrollInputChanged($companyId, $snapshot->id, 'payroll_run_created', 'ready', $snapshot->snapshot_version));

        return $run;
    }

    private function validatePersisted(PayrollInputSnapshot $snapshot, int $actorId, string $idempotencyKey): void
    {
        DB::table('payroll_input_validation_issues')->where('company_id', $snapshot->company_id)->where('payroll_input_snapshot_id', $snapshot->id)->delete();
        $rows = DB::table('payroll_employee_inputs')->where('company_id', $snapshot->company_id)->where('payroll_input_snapshot_id', $snapshot->id)->get();
        $blocking = 0;
        foreach ($rows as $row) {
            $issues = json_decode(is_string($row->readiness_issues ?? null) ? $row->readiness_issues : '[]', true);
            foreach (is_array($issues) ? $issues : [] as $code) {
                if (! is_string($code)) {
                    continue;
                }
                $blocking++;
                DB::table('payroll_input_validation_issues')->insert(['company_id' => $snapshot->company_id, 'payroll_input_snapshot_id' => $snapshot->id, 'employee_id' => is_numeric($row->employee_id ?? null) ? (int) $row->employee_id : null, 'code' => $code, 'severity' => 'error', 'scope' => 'employee', 'is_blocking' => true, 'safe_message' => match ($code) {
                    'missing_assignment' => 'Nhân viên chưa có assignment hợp lệ trong kỳ.',
                    'missing_contract' => 'Nhân viên chưa có hợp đồng hiệu lực trong kỳ.',
                    'OVERTIME_CLASSIFICATION_UNRESOLVED' => 'Không tìm thấy work calendar hiệu lực để phân loại overtime.',
                    'OVERTIME_INTERVAL_INVALID' => 'Khoảng thời gian overtime hoặc payable minutes không hợp lệ.',
                    'OVERTIME_MINUTES_MISMATCH' => 'Tổng phút overtime đã phân loại không khớp nguồn được duyệt.',
                    default => 'Payroll input chưa sẵn sàng.'
                }, 'context' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            }
            $approved = is_numeric($row->approved_overtime_minutes ?? null) ? (int) $row->approved_overtime_minutes : 0;
            $classified = collect(['weekday_overtime_minutes', 'weekend_overtime_minutes', 'holiday_overtime_minutes'])->sum(fn (string $column): int => is_numeric($row->{$column} ?? null) ? (int) $row->{$column} : 0);
            if ($approved !== $classified && ! in_array('OVERTIME_CLASSIFICATION_UNRESOLVED', is_array($issues) ? $issues : [], true)) {
                $blocking++;
                DB::table('payroll_input_validation_issues')->insert(['company_id' => $snapshot->company_id, 'payroll_input_snapshot_id' => $snapshot->id, 'employee_id' => is_numeric($row->employee_id ?? null) ? (int) $row->employee_id : null, 'code' => 'OVERTIME_MINUTES_MISMATCH', 'severity' => 'error', 'scope' => 'employee', 'is_blocking' => true, 'safe_message' => 'Tổng weekday/rest-day/holiday overtime không khớp approved overtime.', 'context' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if ($rows->isEmpty()) {
            $blocking++;
            DB::table('payroll_input_validation_issues')->insert(['company_id' => $snapshot->company_id, 'payroll_input_snapshot_id' => $snapshot->id, 'code' => 'no_eligible_employee', 'severity' => 'error', 'scope' => 'snapshot', 'is_blocking' => true, 'safe_message' => 'Không có nhân viên đủ điều kiện trong Payroll Period.', 'context' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        }
        $sum = fn (string $column): int => (int) $rows->sum(fn (object $row): int => is_numeric($row->{$column} ?? null) ? (int) $row->{$column} : 0);
        $status = $blocking === 0 && $rows->isNotEmpty() ? 'ready' : 'blocked';
        $snapshot->update(['status' => $status, 'employee_count' => $rows->count(), 'attendance_record_count' => $sum('attendance_record_count'), 'leave_record_count' => $sum('leave_record_count'), 'overtime_record_count' => $sum('overtime_record_count'), 'blocking_issue_count' => $blocking, 'warning_issue_count' => 0, 'scheduled_minutes' => $sum('scheduled_minutes'), 'worked_minutes' => $sum('worked_minutes'), 'paid_leave_minutes' => $sum('paid_leave_minutes'), 'unpaid_leave_minutes' => $sum('unpaid_leave_minutes'), 'approved_overtime_minutes' => $sum('approved_overtime_minutes'), 'validated_at' => now()]);
        activity('payroll-input')->performedOn($snapshot)->causedBy($actorId)->event('validated')->withProperties(['company_id' => $snapshot->company_id, 'snapshot_id' => $snapshot->id, 'status' => $status, 'blocking_issues' => $blocking])->log('Payroll input snapshot validated');
    }

    /** @param array<string,int|string> $context */
    private function history(PayrollInputSnapshot $snapshot, string $action, ?string $from, string $to, int $actorId, string $key, array $context = []): void
    {
        DB::table('payroll_input_histories')->insertOrIgnore(['company_id' => $snapshot->company_id, 'payroll_input_snapshot_id' => $snapshot->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'idempotency_key' => $key, 'safe_context' => json_encode($context, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
