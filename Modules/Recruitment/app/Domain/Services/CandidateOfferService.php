<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Recruitment\Application\DTOs\CandidateOfferData;
use Modules\Recruitment\Domain\Enums\ApplicationStage;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;

final readonly class CandidateOfferService
{
    public function __construct(private CandidateOfferStateMachine $states, private RecruitmentHeadcountValidator $headcount) {}

    public function create(int $companyId, CandidateOfferData $data, int $actorId): CandidateOffer
    {
        return DB::transaction(function () use ($companyId, $data, $actorId): CandidateOffer {
            $application = $this->application($companyId, $data->applicationId);
            if (in_array($application->stage, [ApplicationStage::Hired, ApplicationStage::Rejected], true)) {
                throw ValidationException::withMessages(['application_id' => 'Application terminal không thể tạo offer.']);
            }
            $active = CandidateOffer::query()->where('application_id', $application->id)
                ->whereIn('status', ['draft', 'pending_approval', 'approved', 'sent', 'accepted'])->exists();
            if ($active) {
                throw ValidationException::withMessages(['application_id' => 'Application đã có active offer.']);
            }
            if (! preg_match('/^\d{1,16}(\.\d{1,2})?$/', $data->baseSalary)) {
                throw ValidationException::withMessages(['base_salary' => 'Mức lương phải là số thập phân không âm, tối đa hai chữ số lẻ.']);
            }
            if ($data->probationEndDate !== null && $data->probationEndDate->lessThanOrEqualTo($data->startDate)) {
                throw ValidationException::withMessages(['probation_end_date' => 'Ngày kết thúc thử việc phải sau ngày bắt đầu.']);
            }
            if ($data->expiresAt !== null && $data->expiresAt->isPast()) {
                throw ValidationException::withMessages(['expires_at' => 'Thời hạn offer phải ở tương lai.']);
            }
            $requisition = $application->requisition()->with('jobPosition')->firstOrFail();
            $position = JobPosition::query()->where('company_id', $companyId)->findOrFail($requisition->job_position_id);
            $number = 'SCH-OFF-'.now()->format('Ym').'-'.str_pad((string) (CandidateOffer::query()->where('company_id', $companyId)->lockForUpdate()->count() + 1), 5, '0', STR_PAD_LEFT);
            $offer = CandidateOffer::query()->create([
                'company_id' => $companyId,
                'application_id' => $application->id,
                'offer_number' => $number,
                'job_position_id' => $requisition->job_position_id,
                'organization_unit_id' => $position->organization_unit_id,
                'employment_type' => $data->employmentType,
                'base_salary' => $data->baseSalary,
                'currency' => strtoupper($data->currency),
                'start_date' => $data->startDate,
                'probation_end_date' => $data->probationEndDate,
                'expires_at' => $data->expiresAt,
                'terms' => $data->terms,
                'status' => CandidateOfferStatus::Draft->value,
            ]);
            activity('candidate-offer')->performedOn($offer)->causedBy($actorId)->event('created')
                ->withProperties(['company_id' => $companyId, 'application_id' => $application->id, 'offer_number' => $number])
                ->log('Candidate offer created');
            $offer->refresh();
            event(new RecruitmentWorkflowChanged($companyId, 'candidate_offer', $offer->id, 'created', $offer->version, $actorId));

            return $offer;
        }, 3);
    }

    public function transition(int $companyId, int $offerId, CandidateOfferStatus $target, int $actorId, ?string $reason, ?int $expectedVersion): CandidateOffer
    {
        return DB::transaction(function () use ($companyId, $offerId, $target, $actorId, $reason, $expectedVersion): CandidateOffer {
            $offer = CandidateOffer::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($offerId);
            if ($expectedVersion !== null && $offer->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Offer đã thay đổi. Vui lòng tải lại.']);
            }
            $from = $offer->status;
            if ($from === $target && in_array($target, [CandidateOfferStatus::Accepted, CandidateOfferStatus::Declined, CandidateOfferStatus::Withdrawn], true)) {
                return $offer;
            }
            $this->states->assert($from, $target);
            if ($target === CandidateOfferStatus::Approved && $offer->submitted_by === $actorId) {
                throw ValidationException::withMessages(['offer' => 'Người submit không được tự approve offer.']);
            }
            if (in_array($target, [CandidateOfferStatus::Withdrawn], true) && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Thao tác này bắt buộc có lý do.']);
            }
            $application = $this->application($companyId, $offer->application_id);
            $requisition = RecruitmentRequisition::query()->lockForUpdate()->findOrFail($application->recruitment_requisition_id);
            $changes = ['status' => $target->value, 'version' => $offer->version + 1];
            if ($target === CandidateOfferStatus::PendingApproval) {
                $changes += ['submitted_by' => $actorId, 'submitted_at' => now()];
            } elseif ($target === CandidateOfferStatus::Approved) {
                $this->headcount->assertAvailable($companyId, $offer->job_position_id, 0, false, null, $requisition->id);
                $changes += ['approved_by' => $actorId, 'approved_at' => now()];
            } elseif ($target === CandidateOfferStatus::Sent) {
                if ($offer->expires_at !== null && $offer->expires_at->isPast()) {
                    throw ValidationException::withMessages(['expires_at' => 'Offer đã hết hạn và không thể gửi.']);
                }
                $changes += ['sent_by' => $actorId, 'sent_at' => now()];
            } elseif ($target === CandidateOfferStatus::Accepted) {
                if ($offer->expires_at !== null && $offer->expires_at->isPast()) {
                    throw ValidationException::withMessages(['expires_at' => 'Offer đã hết hạn.']);
                }
                $reserved = CandidateOffer::query()->where('application_id', '<>', $application->id)
                    ->whereHas('application', static fn ($query) => $query->where('recruitment_requisition_id', $requisition->id))
                    ->where('reservation_status', 'reserved')->count();
                if ($reserved >= $requisition->vacancies) {
                    throw ValidationException::withMessages(['offer' => 'Requisition không còn vacancy để reserve.']);
                }
                $changes += ['accepted_at' => now(), 'reservation_status' => 'reserved', 'reserved_at' => now(), 'released_at' => null];
                if ($application->stage !== ApplicationStage::Offer) {
                    $this->moveApplicationToOffer($companyId, $application, $actorId);
                }
            } elseif ($target === CandidateOfferStatus::Declined) {
                $changes += ['declined_at' => now(), 'decline_reason' => $reason];
            } elseif ($target === CandidateOfferStatus::Withdrawn) {
                $changes += ['withdrawn_at' => now(), 'withdraw_reason' => $reason];
            } elseif ($target === CandidateOfferStatus::Expired) {
                $changes['released_at'] = now();
            }
            if (in_array($target, [CandidateOfferStatus::Declined, CandidateOfferStatus::Withdrawn, CandidateOfferStatus::Expired], true) && $offer->reservation_status === 'reserved') {
                $changes += ['reservation_status' => 'released', 'released_at' => now()];
            }
            $offer->update($changes);
            activity('candidate-offer')->performedOn($offer)->causedBy($actorId)->event($target->value)
                ->withProperties(['company_id' => $companyId, 'from' => $from->value, 'to' => $target->value, 'reason' => $reason])
                ->log('Candidate offer status changed');
            event(new RecruitmentWorkflowChanged($companyId, 'candidate_offer', $offer->id, $target->value, $offer->version, $actorId));

            return $offer->refresh();
        }, 3);
    }

    /** @return LengthAwarePaginator<int, CandidateOffer> */
    public function list(int $companyId): LengthAwarePaginator
    {
        return CandidateOffer::query()->with(['application.candidate:id,full_name', 'jobPosition:id,title'])
            ->where('company_id', $companyId)->latest()->paginate(20);
    }

    public function detail(int $companyId, int $offerId): CandidateOffer
    {
        return CandidateOffer::query()->with(['application.candidate', 'application.requisition', 'jobPosition:id,title,organization_unit_id', 'organizationUnit:id,name'])
            ->where('company_id', $companyId)->findOrFail($offerId);
    }

    private function application(int $companyId, int $applicationId): Application
    {
        return Application::query()->whereHas('requisition.jobPosition', static fn ($query) => $query->where('company_id', $companyId))
            ->lockForUpdate()->findOrFail($applicationId);
    }

    private function moveApplicationToOffer(int $companyId, Application $application, int $actorId): void
    {
        $before = $application->stage->value;
        $application->update(['stage' => ApplicationStage::Offer->value, 'stage_changed_at' => now(), 'stage_reason' => 'Candidate offer accepted', 'version' => $application->version + 1]);
        DB::table('application_stage_histories')->insert([
            'company_id' => $companyId, 'application_id' => $application->id, 'from_stage' => $before,
            'to_stage' => ApplicationStage::Offer->value, 'reason' => 'Candidate offer accepted',
            'actor_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
