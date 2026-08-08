<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class GoalManagementService
{
    public function __construct(private PerformanceSupport $support) {}

    public function assign(PerformanceActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $existing = DB::table('employee_goals')->where('company_id', $d->companyId)->where('idempotency_key', $d->idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            } $employee = DB::table('employees')->where('company_id', $d->companyId)->where('id', $d->employeeId)->exists();
            $cycle = DB::table('performance_cycles')->where('company_id', $d->companyId)->where('id', $d->cycleId)->lockForUpdate()->first();
            if (! $employee || $cycle === null) {
                abort(404);
            }
            $cycleData = get_object_vars($cycle);
            $cycleStatus = is_string($cycleData['talent_status'] ?? null) ? $cycleData['talent_status'] : 'draft';
            $cycleId = is_int($cycleData['id'] ?? null) ? $cycleData['id'] : 0;
            if (in_array($cycleStatus, ['approved', 'closed'], true)) {
                throw ValidationException::withMessages(['status' => 'Chu kỳ đã khóa mục tiêu.']);
            } $weight = is_numeric($d->value) ? (float) $d->value : -1;
            if ($weight <= 0 || $weight > 100 || $d->title === null) {
                throw ValidationException::withMessages(['weight' => 'Trọng số phải trong khoảng 0–100.']);
            } $id = (int) DB::table('employee_goals')->insertGetId(['company_id' => $d->companyId, 'employee_id' => $d->employeeId, 'cycle_id' => $d->cycleId, 'title' => $d->title, 'description' => $d->description, 'weight' => number_format($weight, 2, '.', ''), 'progress' => '0.00', 'status' => 'assigned', 'idempotency_key' => $d->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
            if ($cycleStatus === 'draft') {
                DB::table('performance_cycles')->where('id', $cycleId)->update(['talent_status' => 'assigned', 'updated_at' => now()]);
            } $this->support->outbox($d->companyId, 'GoalAssigned', 'employee_goal', $id, ['employee_id' => $d->employeeId, 'cycle_id' => $d->cycleId, 'title' => $d->title]);

            return $id;
        }, 3);
    }

    public function progress(PerformanceActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $goal = DB::table('employee_goals')->where('company_id', $d->companyId)->where('id', $d->goalId)->lockForUpdate()->first();
            if ($goal === null) {
                abort(404);
            }
            $goalData = get_object_vars($goal);
            $goalId = is_int($goalData['id'] ?? null) ? $goalData['id'] : 0;
            $cycleId = is_int($goalData['cycle_id'] ?? null) ? $goalData['cycle_id'] : 0;
            $cycleStatus = DB::table('performance_cycles')->where('company_id', $d->companyId)->where('id', $cycleId)->lockForUpdate()->value('talent_status');
            if (in_array($cycleStatus, ['approved', 'closed'], true)) {
                throw ValidationException::withMessages(['status' => 'Chu kỳ đã khóa mục tiêu.']);
            }
            $existing = DB::table('goal_progress_history')->where('goal_id', $goalId)->where('idempotency_key', $d->idempotencyKey)->exists();
            if ($existing) {
                return $goalId;
            } $value = is_numeric($d->value) ? (float) $d->value : -1;
            if ($value < 0 || $value > 100) {
                throw ValidationException::withMessages(['progress' => 'Tiến độ phải trong khoảng 0–100.']);
            }
            $oldProgress = $goalData['progress'] ?? null;
            $payload = ['old' => is_string($oldProgress) ? $oldProgress : '0.00', 'new' => number_format($value, 2, '.', '')];
            DB::table('goal_progress_history')->insert(['goal_id' => $goalId, 'old_value' => $payload['old'], 'new_value' => $payload['new'], 'changed_by' => $d->actorId, 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_at' => now()]);
            DB::table('employee_goals')->where('id', $goalId)->update(['progress' => $payload['new'], 'status' => $value >= 100 ? 'completed' : 'in_progress', 'updated_at' => now()]);
            $this->support->outbox($d->companyId, 'GoalProgressUpdated', 'employee_goal', $goalId, $payload);

            return $goalId;
        }, 3);
    }
}
