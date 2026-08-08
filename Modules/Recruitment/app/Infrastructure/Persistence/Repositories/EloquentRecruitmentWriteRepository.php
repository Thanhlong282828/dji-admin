<?php

namespace Modules\Recruitment\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;
use Modules\Recruitment\Domain\Repositories\RecruitmentWriteRepositoryInterface;
use Modules\Recruitment\Domain\Services\ApplicationStageMachine;
use Modules\Recruitment\Domain\Services\CandidateFingerprintService;
use Modules\Recruitment\Domain\Services\RecruitmentHeadcountValidator;
use Modules\Recruitment\Domain\Services\RequisitionStateMachine;

final readonly class EloquentRecruitmentWriteRepository implements RecruitmentWriteRepositoryInterface
{
    public function __construct(
        private RequisitionStateMachine $requisitions,
        private ApplicationStageMachine $stages,
        private CandidateFingerprintService $fingerprints,
        private RecruitmentHeadcountValidator $headcount,
    ) {}

    public function createRequisition(int $companyId, array $data): array
    {
        return DB::transaction(function () use ($companyId, $data): array {
            $positionId = $this->requiredInt($data, 'job_position_id');
            $requesterId = $this->requiredInt($data, 'requested_by');
            $vacancies = $this->requiredInt($data, 'vacancies');
            JobPosition::query()->where('company_id', $companyId)->findOrFail($positionId);
            Employee::query()->where('company_id', $companyId)->findOrFail($requesterId);
            $this->headcount->assertAvailable($companyId, $positionId, $vacancies);

            return $this->attributes(RecruitmentRequisition::query()->create([...$data, 'status' => 'draft']));
        });
    }

    public function transitionRequisition(int $companyId, int $id, string $status, int $actorId, ?string $reason): array
    {
        return DB::transaction(function () use ($companyId, $id, $status, $actorId, $reason): array {
            $r = RecruitmentRequisition::query()->whereHas('jobPosition', fn ($q) => $q->where('company_id', $companyId))->lockForUpdate()->findOrFail($id);
            $this->requisitions->assert($r->status->value, $status);
            if (in_array($status, ['approved', 'open'], true)) {
                $this->headcount->assertAvailable($companyId, $r->job_position_id, $r->vacancies, false, null, $r->id);
            }

            $before = $r->status->value;
            $changes = ['status' => $status, 'decision_reason' => $reason, 'version' => $r->version + 1];
            if ($status === 'approved') {
                $changes['approved_by'] = $actorId;
                $changes['approved_at'] = now();
            }
            $r->update($changes);
            activity('recruitment-requisition')
                ->performedOn($r)
                ->causedBy($actorId)
                ->event('status_changed')
                ->withProperties(['company_id' => $companyId, 'from' => $before, 'to' => $status, 'reason' => $reason])
                ->log('Recruitment requisition status changed');

            return $this->attributes($r->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCandidate(int $companyId, array $data): array
    {
        $email = $this->requiredString($data, 'email');
        $name = $this->requiredString($data, 'full_name');
        $phoneValue = $data['phone'] ?? null;
        $phone = is_string($phoneValue) ? preg_replace('/[^\d+]/', '', $phoneValue) : null;
        $data['email'] = mb_strtolower(trim($email));
        $data['phone'] = $phone;
        $data['profile_fingerprint'] = $this->fingerprints->make($name, $data['email'], $phone);

        return $this->attributes(Candidate::query()->create([...$data, 'company_id' => $companyId]));
    }

    public function createApplication(int $companyId, array $data, int $actorId): array
    {
        return DB::transaction(function () use ($companyId, $data, $actorId): array {
            $requisitionId = $this->requiredInt($data, 'recruitment_requisition_id');
            $candidateId = $this->requiredInt($data, 'candidate_id');
            $r = RecruitmentRequisition::query()->whereHas('jobPosition', fn ($q) => $q->where('company_id', $companyId))->lockForUpdate()->findOrFail($requisitionId);
            if ($r->status->value !== 'open') {
                throw ValidationException::withMessages(['requisition' => 'Chỉ yêu cầu đang mở mới nhận ứng viên.']);
            }

            $candidate = Candidate::query()
                ->where('company_id', $companyId)
                ->findOrFail($candidateId);
            if (! $candidate->consent) {
                throw ValidationException::withMessages(['candidate' => 'Ứng viên chưa đồng ý xử lý dữ liệu.']);
            }

            $application = Application::query()->create([...$data, 'stage' => 'received', 'stage_changed_at' => now()]);
            DB::table('application_stage_histories')->insert([
                'company_id' => $companyId,
                'application_id' => $application->id,
                'from_stage' => null,
                'to_stage' => 'received',
                'reason' => 'Application created',
                'actor_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->attributes($application);
        });
    }

    public function moveApplication(int $companyId, int $id, string $stage, int $actorId, ?int $expectedVersion, ?string $reason): array
    {
        return DB::transaction(function () use ($companyId, $id, $stage, $actorId, $expectedVersion, $reason): array {
            $a = Application::query()->whereHas('requisition.jobPosition', fn ($q) => $q->where('company_id', $companyId))->lockForUpdate()->findOrFail($id);
            if ($expectedVersion !== null && $a->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Application đã được cập nhật bởi người khác. Vui lòng tải lại dữ liệu.']);
            }
            $before = $a->stage->value;
            $this->stages->assert($a->stage->value, $stage);
            $a->update(['stage' => $stage, 'stage_reason' => $reason, 'stage_changed_at' => now(), 'version' => $a->version + 1]);
            DB::table('application_stage_histories')->insert([
                'company_id' => $companyId,
                'application_id' => $a->id,
                'from_stage' => $before,
                'to_stage' => $stage,
                'reason' => $reason,
                'actor_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            activity('application')
                ->performedOn($a)
                ->causedBy($actorId)
                ->event('stage_changed')
                ->withProperties(['company_id' => $companyId, 'from' => $before, 'to' => $stage, 'reason' => $reason])
                ->log('Application stage changed');

            return $this->attributes($a->refresh());
        });
    }

    /** @param array<string, mixed> $data */
    private function requiredInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw ValidationException::withMessages([$key => 'Giá trị không hợp lệ.']);
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([$key => 'Giá trị không hợp lệ.']);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function attributes(Model $model): array
    {
        return $model->getAttributes();
    }
}
