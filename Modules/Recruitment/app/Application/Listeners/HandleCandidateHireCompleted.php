<?php

namespace Modules\Recruitment\Application\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\Recruitment\Domain\Events\CandidateHireCompleted;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;

final class HandleCandidateHireCompleted
{
    public function handle(CandidateHireCompleted $event): void
    {
        foreach (['recruitment', 'employee', 'onboarding', 'organization'] as $context) {
            $key = "schema_admin:{$context}:{$event->companyId}:version";
            Cache::add($key, 0, now()->addYear());
            Cache::increment($key);
        }

        event(new RecruitmentWorkflowChanged(
            $event->companyId,
            'candidate_offer',
            $event->offerId,
            'hired',
            $event->version,
            $event->actorId,
        ));
    }
}
