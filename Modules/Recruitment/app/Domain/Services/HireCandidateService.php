<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\DTOs\EmployeeAssignmentData;
use Modules\Employee\Application\DTOs\EmploymentContractData;
use Modules\Employee\Domain\Enums\AssignmentType;
use Modules\Employee\Domain\Enums\EmploymentStatus;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmploymentContract;
use Modules\Employee\Domain\Services\EmployeeAssignmentService;
use Modules\Employee\Domain\Services\EmploymentContractService;
use Modules\Onboarding\Application\DTOs\OnboardingCaseData;
use Modules\Onboarding\Domain\Services\OnboardingCaseCreationService;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Recruitment\Application\DTOs\HireCandidateData;
use Modules\Recruitment\Application\DTOs\HireCandidateResult;
use Modules\Recruitment\Domain\Enums\ApplicationStage;
use Modules\Recruitment\Domain\Events\CandidateHireCompleted;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Models\HireTransactionMarker;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;

final readonly class HireCandidateService
{
    public function __construct(
        private HireCandidateEligibilityValidator $eligibility,
        private EmployeeAssignmentService $assignments,
        private EmploymentContractService $contracts,
        private OnboardingCaseCreationService $onboarding,
    ) {}

    public function hire(int $companyId, int $offerId, HireCandidateData $data, int $actorId): HireCandidateResult
    {
        try {
            return $this->execute($companyId, $offerId, $data, $actorId);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['hire' => 'Dữ liệu Hire bị xung đột với một giao dịch đã hoàn tất. Vui lòng tải lại.']);
            }

            throw $exception;
        }
    }

    private function execute(int $companyId, int $offerId, HireCandidateData $data, int $actorId): HireCandidateResult
    {
        return DB::transaction(function () use ($companyId, $offerId, $data, $actorId): HireCandidateResult {
            $offer = CandidateOffer::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($offerId);
            $existing = HireTransactionMarker::query()->where('offer_id', $offerId)->lockForUpdate()->first();
            if ($existing !== null && $existing->status === 'completed') {
                return $this->completedResult($companyId, $offer, $existing);
            }
            $sameKey = HireTransactionMarker::query()->where('idempotency_key', $data->idempotencyKey)->lockForUpdate()->first();
            if ($sameKey !== null && (int) $sameKey->offer_id !== $offerId) {
                throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key đã được dùng cho một offer khác.']);
            }

            $application = Application::query()->lockForUpdate()->findOrFail($offer->application_id);
            $candidate = Candidate::query()->lockForUpdate()->findOrFail($application->candidate_id);
            $requisition = RecruitmentRequisition::query()->lockForUpdate()->findOrFail($application->recruitment_requisition_id);
            $position = JobPosition::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($offer->job_position_id);
            $unit = OrganizationUnit::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($offer->organization_unit_id);
            $this->eligibility->validate($companyId, $offer, $application, $requisition, $position, $unit, $data);

            $marker = $existing ?? HireTransactionMarker::query()->create([
                'company_id' => $companyId,
                'offer_id' => $offerId,
                'idempotency_key' => $data->idempotencyKey,
                'status' => 'processing',
                'actor_id' => $actorId,
            ]);
            $managerId = $this->managerEmployeeId($companyId, $data->managerUserId);
            $employeeCode = $this->employeeCode($data->employeeCode);
            $profile = $this->candidateProfile($candidate);
            $employee = Employee::query()->create([
                'company_id' => $companyId,
                'manager_id' => $managerId,
                'employee_code' => $employeeCode,
                'full_name' => $candidate->full_name,
                'personal_email' => $candidate->email,
                'phone' => $candidate->phone,
                'nationality' => $profile['nationality'],
                'join_date' => $data->startDate->toDateString(),
                'employment_status' => EmploymentStatus::Preboarding->value,
                'employment_type' => $data->employmentType->value,
                'source' => 'recruitment',
                'source_candidate_id' => $candidate->id,
                'source_application_id' => $application->id,
                'source_offer_id' => $offer->id,
            ]);

            $assignment = $this->assignments->create($companyId, (int) $employee->id, new EmployeeAssignmentData(
                (int) $unit->id,
                (int) $position->id,
                $managerId,
                AssignmentType::Primary,
                '100.00',
                $data->workLocation,
                $data->startDate,
                null,
            ), $actorId);
            $contract = $this->contracts->create($companyId, (int) $employee->id, new EmploymentContractData(
                'SCH-CTR-'.strtoupper(Str::random(12)),
                $data->contractType,
                $data->startDate,
                $data->contractEndDate,
                $offer->base_salary,
                $offer->currency,
            ), $actorId);
            $contract->update(['source_offer_id' => $offer->id]);

            $onboarding = $this->onboarding->create(new OnboardingCaseData(
                $companyId,
                (int) $employee->id,
                (int) $application->id,
                (int) $offer->id,
                (int) $requisition->id,
                (int) $position->id,
                (int) $unit->id,
                $actorId,
                $data->startDate,
                $data->onboardingTemplateCode,
                $data->employmentType->value,
                $actorId,
            ));

            $beforeStage = $application->stage->value;
            $application->update([
                'stage' => ApplicationStage::Hired->value,
                'stage_changed_at' => now(),
                'stage_reason' => $data->reason,
                'hired_employee_id' => $employee->id,
                'hiring_reference' => $marker->idempotency_key,
                'hired_at' => now(),
                'version' => $application->version + 1,
            ]);
            DB::table('application_stage_histories')->insert([
                'company_id' => $companyId,
                'application_id' => $application->id,
                'from_stage' => $beforeStage,
                'to_stage' => ApplicationStage::Hired->value,
                'reason' => $data->reason.' [employee_id='.(int) $employee->id.', offer_id='.(int) $offer->id.']',
                'actor_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $candidate->update(['status' => 'hired', 'hired_employee_id' => $employee->id, 'hired_application_id' => $application->id, 'hired_at' => now()]);
            $offer->update(['reservation_status' => 'consumed', 'hired_employee_id' => $employee->id, 'hired_at' => now(), 'version' => $offer->version + 1]);
            $requisition->update(['filled_hires' => $requisition->filled_hires + 1, 'version' => $requisition->version + 1]);
            $marker->update(['employee_id' => $employee->id, 'onboarding_case_id' => $onboarding->caseId, 'status' => 'completed', 'completed_at' => now()]);

            activity('candidate-hire')->performedOn($marker)->causedBy($actorId)->event('completed')->withProperties([
                'company_id' => $companyId,
                'candidate_id' => (int) $candidate->id,
                'application_id' => (int) $application->id,
                'offer_id' => (int) $offer->id,
                'employee_id' => (int) $employee->id,
                'onboarding_case_id' => $onboarding->caseId,
                'reason' => $data->reason,
                'idempotency_hash' => hash('sha256', $data->idempotencyKey),
                'before' => ['application_stage' => $beforeStage, 'offer_status' => 'accepted', 'reservation_status' => 'reserved'],
                'after' => ['application_stage' => 'hired', 'offer_status' => 'accepted', 'reservation_status' => 'consumed', 'onboarding_status' => 'preboarding'],
            ])->log('Candidate hired and onboarding initialized');

            event(new CandidateHireCompleted($companyId, (int) $offer->id, (int) $application->id, (int) $candidate->id, (int) $employee->id, $onboarding->caseId, (int) $offer->version, $actorId));

            return new HireCandidateResult($companyId, (int) $candidate->id, (int) $application->id, (int) $offer->id, (int) $employee->id, $employeeCode, (int) $assignment->id, (int) $contract->id, $onboarding->caseId, false);
        }, 3);
    }

    private function completedResult(int $companyId, CandidateOffer $offer, HireTransactionMarker $marker): HireCandidateResult
    {
        $employee = Employee::query()->where('company_id', $companyId)->findOrFail($marker->employee_id);
        $application = Application::query()->findOrFail($offer->application_id);
        $assignmentId = $this->requiredNumericId($employee->assignments()->where('assignment_type', AssignmentType::Primary->value)->value('id'), 'primary assignment');
        $contractId = $this->requiredNumericId(EmploymentContract::query()->where('source_offer_id', $offer->id)->value('id'), 'employment contract');

        return new HireCandidateResult($companyId, (int) $application->candidate_id, (int) $application->id, (int) $offer->id, (int) $employee->id, (string) $employee->employee_code, $assignmentId, $contractId, (int) $marker->onboarding_case_id, true);
    }

    private function managerEmployeeId(int $companyId, ?int $managerUserId): ?int
    {
        if ($managerUserId === null) {
            return null;
        }

        $value = Employee::query()->where('company_id', $companyId)->where('user_id', $managerUserId)->value('id');
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['manager_user_id' => 'Quản lý được chọn chưa có hồ sơ nhân viên trong company.']);
        }

        return (int) $value;
    }

    private function requiredNumericId(mixed $value, string $entity): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['hire' => "Completed hire is missing {$entity}."]);
        }

        return (int) $value;
    }

    private function employeeCode(?string $requested): string
    {
        $code = $requested === null || trim($requested) === '' ? 'SCH-'.strtoupper(Str::random(10)) : strtoupper(trim($requested));
        if (Employee::query()->where('employee_code', $code)->exists()) {
            throw ValidationException::withMessages(['employee_number' => 'Mã nhân viên đã tồn tại.']);
        }

        return $code;
    }

    /** @return array{nationality:string} */
    private function candidateProfile(Candidate $candidate): array
    {
        $profile = $candidate->profile;
        $nationality = is_array($profile) && is_string($profile['nationality'] ?? null) ? trim($profile['nationality']) : '';

        return ['nationality' => $nationality === '' ? 'VN' : $nationality];
    }
}
