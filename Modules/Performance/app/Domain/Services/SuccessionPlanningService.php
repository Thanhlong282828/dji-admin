<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Performance\Application\DTOs\CareerActionData;
use Modules\Performance\Domain\Enums\SuccessionStatus;
use Modules\Performance\Domain\Events\SuccessorApproved;
use Modules\Performance\Domain\Events\SuccessorNominated;

final readonly class SuccessionPlanningService
{
    public function __construct(private CareerSupport $support, private CareerStateMachine $states, private ReadinessAssessmentService $readiness) {}

    public function create(CareerActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $existing = DB::table('succession_plans')->where('company_id', $d->companyId)->where('idempotency_key', $d->idempotencyKey)->value('id');
            if (is_int($existing)) {
                return $existing;
            }if (! DB::table('job_positions')->where('company_id', $d->companyId)->where('id', $d->positionId)->exists()) {
                abort(404);
            }$payload = ['position_id' => $d->positionId];
            $id = (int) DB::table('succession_plans')->insertGetId(['company_id' => $d->companyId, 'position_id' => $d->positionId, 'status' => 'draft', 'owner_id' => $d->actorId, 'version' => 1, 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
            $this->support->record($d->companyId, $d->actorId, $d->idempotencyKey, 'succession_plan', $id, 'SuccessionPlanCreated', null, $payload);

            return $id;
        }, 3);
    }

    public function nominate(CareerActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $p = $this->plan($d);
            if (! DB::table('employees')->where('company_id', $d->companyId)->where('id', $d->employeeId)->exists()) {
                abort(404);
            }$existing = DB::table('succession_candidates')->where('succession_plan_id', $p['id'])->where('employee_id', $d->employeeId)->value('id');
            if (is_int($existing)) {
                return $existing;
            }$this->states->succession(SuccessionStatus::from($p['status']), SuccessionStatus::Nominated);
            $payload = ['employee_id' => $d->employeeId, 'readiness_level' => $d->readinessLevel ?? 'not_ready'];
            $id = (int) DB::table('succession_candidates')->insertGetId(['succession_plan_id' => $p['id'], 'employee_id' => $d->employeeId, 'readiness_level' => $payload['readiness_level'], 'score' => '0.00', 'reason' => $d->reason, 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('succession_plans')->where('id', $p['id'])->update(['status' => 'nominated', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            $this->support->record($d->companyId, $d->actorId, $d->idempotencyKey, 'succession_plan', $p['id'], SuccessorNominated::NAME, $d->employeeId, $payload);

            return $id;
        }, 3);
    }

    public function assess(CareerActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $p = $this->plan($d);
            $this->states->succession(SuccessionStatus::from($p['status']), SuccessionStatus::Assessed);
            $candidate = DB::table('succession_candidates')->where('succession_plan_id', $p['id'])->where('id', $d->candidateId)->lockForUpdate()->first();
            if ($candidate === null) {
                abort(404);
            }$cv = get_object_vars($candidate);
            $employee = is_int($cv['employee_id'] ?? null) ? $cv['employee_id'] : 0;
            $assessment = $this->readiness->assess(new CareerActionData($d->companyId, $d->actorId, $d->idempotencyKey, candidateId: $d->candidateId, employeeId: $employee, readinessLevel: $d->readinessLevel, score: $d->score));
            DB::table('succession_candidates')->where('id', $d->candidateId)->update(['readiness_level' => $d->readinessLevel, 'score' => $d->score, 'reason' => $d->reason, 'updated_at' => now()]);
            DB::table('succession_plans')->where('id', $p['id'])->update(['status' => 'assessed', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            $this->support->record($d->companyId, $d->actorId, $d->idempotencyKey.'-history', 'succession_plan', $p['id'], 'ReadinessAssessed', $employee, ['assessment_id' => $assessment, 'score' => $d->score]);

            return $assessment;
        }, 3);
    }

    public function approve(CareerActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $p = $this->plan($d);
            if ($p['status'] === 'approved') {
                return $p['id'];
            }if ($p['owner_id'] === $d->actorId) {
                throw ValidationException::withMessages(['actor' => 'Người tạo không được tự phê duyệt succession plan.']);
            }$this->states->succession(SuccessionStatus::from($p['status']), SuccessionStatus::Approved);
            DB::table('succession_plans')->where('id', $p['id'])->update(['status' => 'approved', 'approved_by' => $d->actorId, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            $this->support->record($d->companyId, $d->actorId, $d->idempotencyKey, 'succession_plan', $p['id'], SuccessorApproved::NAME, null, ['status' => 'approved']);

            return $p['id'];
        }, 3);
    }

    /** @return array{id:int,status:string,owner_id:int} */
    private function plan(CareerActionData $d): array
    {
        $r = DB::table('succession_plans')->where('company_id', $d->companyId)->where('id', $d->successionPlanId)->lockForUpdate()->first();
        if ($r === null) {
            abort(404);
        }$v = get_object_vars($r);
        if (! is_int($v['id'] ?? null) || ! is_int($v['owner_id'] ?? null) || ! is_string($v['status'] ?? null)) {
            throw ValidationException::withMessages(['plan' => 'Dữ liệu succession plan không hợp lệ.']);
        }

        return ['id' => $v['id'], 'status' => $v['status'], 'owner_id' => $v['owner_id']];
    }
}
