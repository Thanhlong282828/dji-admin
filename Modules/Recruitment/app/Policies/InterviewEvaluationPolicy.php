<?php

namespace Modules\Recruitment\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Domain\Models\InterviewEvaluation;

final readonly class InterviewEvaluationPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function update(User $user, InterviewEvaluation $evaluation): bool
    {
        return $user->can('interview.evaluate')
            && $evaluation->interviewer_user_id === $user->id
            && $evaluation->isDraft()
            && $this->tenant->canAccess($user, $evaluation->company_id);
    }

    public function reopen(User $user, InterviewEvaluation $evaluation): bool
    {
        return $user->can('interview.reopen-evaluation')
            && $evaluation->isSubmitted()
            && $this->tenant->canAccess($user, $evaluation->company_id);
    }
}
