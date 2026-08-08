<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Performance\Application\DTOs\CareerActionData;
use Modules\Performance\Domain\Enums\CareerPlanStatus;
use Modules\Performance\Domain\Events\CareerPlanCreated;
use Modules\Performance\Domain\Events\CareerProgressUpdated;

final readonly class CareerPathService
{
    public function __construct(private CareerSupport $support, private CareerStateMachine $states) {}

    public function create(CareerActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $existing = DB::table('career_paths')->where('company_id', $d->companyId)->where('idempotency_key', $d->idempotencyKey)->value('id');
            if (is_int($existing)) {
                return $existing;
            }if ($d->name === null || $d->steps === []) {
                throw ValidationException::withMessages(['steps' => 'Career path cần tên và ít nhất một step.']);
            }
            $positions = [];
            foreach ($d->steps as $step) {
                $positions[] = $step['position_id'];
            }
            $count = DB::table('job_positions')->where('company_id', $d->companyId)->whereIn('id', $positions)->count();
            if ($count !== count(array_unique($positions))) {
                abort(404);
            }$payload = ['name' => $d->name, 'description' => $d->description, 'steps' => $d->steps];
            $id = (int) DB::table('career_paths')->insertGetId(['company_id' => $d->companyId, 'name' => $d->name, 'description' => $d->description, 'version' => 1, 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_by' => $d->actorId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($d->steps as $i => $step) {
                DB::table('career_path_steps')->insert(['career_path_id' => $id, 'position_id' => $step['position_id'], 'sequence' => $i + 1, 'required_level' => $step['required_level'], 'expected_duration_months' => $step['expected_duration_months'], 'checksum' => $this->support->checksum($step), 'created_at' => now(), 'updated_at' => now()]);
            }$this->support->record($d->companyId, $d->actorId, $d->idempotencyKey, 'career_path', $id, CareerPlanCreated::NAME, null, $payload);

            return $id;
        }, 3);
    }

    public function assign(CareerActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $existing = DB::table('employee_career_plans')->where('company_id', $d->companyId)->where('idempotency_key', $d->idempotencyKey)->value('id');
            if (is_int($existing)) {
                return $existing;
            }$employee = DB::table('employees')->where('company_id', $d->companyId)->where('id', $d->employeeId)->exists();
            $path = DB::table('career_paths')->where('company_id', $d->companyId)->where('id', $d->careerPathId)->exists();
            if (! $employee || ! $path) {
                abort(404);
            }$target = $d->targetStep ?? 1;
            $payload = ['employee_id' => $d->employeeId, 'career_path_id' => $d->careerPathId, 'current_step' => 1, 'target_step' => $target];
            $id = (int) DB::table('employee_career_plans')->insertGetId(['company_id' => $d->companyId, 'employee_id' => $d->employeeId, 'career_path_id' => $d->careerPathId, 'current_step' => 1, 'target_step' => $target, 'status' => 'active', 'version' => 1, 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_by' => $d->actorId, 'created_at' => now(), 'updated_at' => now()]);
            $this->support->record($d->companyId, $d->actorId, $d->idempotencyKey, 'employee_career_plan', $id, CareerPlanCreated::NAME, $d->employeeId, $payload);

            return $id;
        }, 3);
    }

    public function progress(CareerActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $row = DB::table('employee_career_plans')->where('company_id', $d->companyId)->where('id', $d->careerPlanId)->lockForUpdate()->first();
            if ($row === null) {
                abort(404);
            }$v = get_object_vars($row);
            $id = is_int($v['id'] ?? null) ? $v['id'] : 0;
            $employee = is_int($v['employee_id'] ?? null) ? $v['employee_id'] : 0;
            $from = CareerPlanStatus::from(is_string($v['status'] ?? null) ? $v['status'] : 'draft');
            $target = $d->currentStep ?? 0;
            $limit = is_int($v['target_step'] ?? null) ? $v['target_step'] : 0;
            if ($target < 1 || $target > $limit) {
                throw ValidationException::withMessages(['current_step' => 'Step tiến độ không hợp lệ.']);
            }$to = $target === $limit ? CareerPlanStatus::Completed : CareerPlanStatus::Active;
            if ($from !== $to) {
                $this->states->career($from, $to);
            }$payload = ['from_step' => $v['current_step'] ?? 0, 'to_step' => $target, 'status' => $to->value];
            DB::table('employee_career_plans')->where('id', $id)->update(['current_step' => $target, 'status' => $to->value, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            $this->support->record($d->companyId, $d->actorId, $d->idempotencyKey, 'employee_career_plan', $id, CareerProgressUpdated::NAME, $employee, $payload);

            return $id;
        }, 3);
    }
}
