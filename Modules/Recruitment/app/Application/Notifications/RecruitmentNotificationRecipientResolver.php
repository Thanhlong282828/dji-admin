<?php

namespace Modules\Recruitment\Application\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Models\InterviewEvaluation;

final class RecruitmentNotificationRecipientResolver
{
    public function __construct(private readonly TenantAccessService $tenant) {}

    /** @return Collection<int, User> */
    public function resolve(int $companyId, string $entity, int $entityId, string $action, ?int $actorId = null): Collection
    {
        $ids = match ($entity) {
            'interview' => $this->interviewRecipients($companyId, $entityId, $action),
            'interview_evaluation' => $this->evaluationRecipients($companyId, $entityId, $action),
            'candidate_offer' => $this->offerRecipients($companyId, $entityId, $action),
            default => [],
        };
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0 && $id !== $actorId)));
        if ($ids === []) {
            return User::query()->whereKey([])->get();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->whereNotNull('email_verified_at')
            ->get()
            ->filter(fn (User $user): bool => $this->tenant->canAccess($user, $companyId))
            ->values();
    }

    /** @return list<int> */
    private function interviewRecipients(int $companyId, int $interviewId, ?string $action = null): array
    {
        $interview = ApplicationInterview::query()
            ->with(['interviewers:user_id,application_interview_id', 'application.requisition.requester:id,user_id'])
            ->where('company_id', $companyId)->find($interviewId);
        if (! $interview) {
            return [];
        }

        /** @var int|null $scheduledBy */
        $scheduledBy = $interview->getAttribute('scheduled_by');

        $ids = [];
        if ($action !== 'submitted') {
            foreach ($interview->interviewers as $member) {
                $ids[] = $member->user_id;
            }
        }
        if ($scheduledBy && ! in_array($action, ['reminder_24h', 'reminder_1h'], true)) {
            $ids[] = $scheduledBy;
        }
        $requesterUserId = $interview->application?->requisition?->requester?->user_id;
        if ($requesterUserId !== null && ! in_array($action, ['reminder_24h', 'reminder_1h'], true)) {
            $ids[] = $requesterUserId;
        }

        return $ids;
    }

    /** @return list<int> */
    private function evaluationRecipients(int $companyId, int $evaluationId, string $action): array
    {
        $evaluation = InterviewEvaluation::query()->where('company_id', $companyId)->find($evaluationId);
        if (! $evaluation) {
            return [];
        }
        if ($action === 'reopened') {
            return [$evaluation->interviewer_user_id];
        }

        return $this->interviewRecipients($companyId, $evaluation->application_interview_id, $action);
    }

    /** @return list<int> */
    private function offerRecipients(int $companyId, int $offerId, string $action): array
    {
        $offer = CandidateOffer::query()->with('application.requisition.requester:id,user_id')->where('company_id', $companyId)->find($offerId);
        if (! $offer) {
            return [];
        }
        if ($action === 'pending_approval') {
            $ids = [];
            foreach (User::permission('candidate-offer.approve')->get(['id']) as $user) {
                if ($this->tenant->canAccess($user, $companyId)) {
                    $ids[] = $user->id;
                }
            }

            return $ids;
        }

        return array_values(array_filter([
            $offer->submitted_by,
            $offer->sent_by,
            $offer->application?->requisition?->requester?->user_id,
        ], static fn (?int $id): bool => $id !== null));
    }
}
