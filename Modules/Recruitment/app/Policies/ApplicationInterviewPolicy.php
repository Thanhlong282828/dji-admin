<?php

namespace Modules\Recruitment\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Domain\Models\ApplicationInterview;

final readonly class ApplicationInterviewPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function view(User $user, ApplicationInterview $interview): bool
    {
        return $user->can('interview.view') && $this->tenant->canAccess($user, $interview->company_id);
    }

    public function viewPanelSummary(User $user, ApplicationInterview $interview): bool
    {
        return $user->can('interview.view-panel-summary') && $this->tenant->canAccess($user, $interview->company_id);
    }

    public function schedule(User $user, int $companyId): bool
    {
        return $user->can('interview.schedule') && $this->tenant->canAccess($user, $companyId);
    }

    public function transition(User $user, ApplicationInterview $interview, string $permission): bool
    {
        return $user->can($permission) && $this->tenant->canAccess($user, $interview->company_id);
    }
}
