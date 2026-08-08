<?php

namespace Modules\Recruitment\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;
use Modules\Recruitment\Domain\Models\CandidateOffer;

final readonly class CandidateOfferPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function view(User $user, CandidateOffer $offer): bool
    {
        return $user->can('candidate-offer.view') && $this->tenant->canAccess($user, $offer->company_id);
    }

    public function create(User $user, int $companyId): bool
    {
        return $user->can('candidate-offer.create') && $this->tenant->canAccess($user, $companyId);
    }

    public function transition(User $user, CandidateOffer $offer, string $permission): bool
    {
        return $user->can($permission) && $this->tenant->canAccess($user, $offer->company_id);
    }

    public function viewSalary(User $user, CandidateOffer $offer): bool
    {
        return $this->view($user, $offer) && $user->can('candidate-offer.view-salary');
    }

    public function hire(User $user, CandidateOffer $offer): bool
    {
        return $user->can('candidate-offer.hire')
            && $this->tenant->canAccess($user, $offer->company_id)
            && $offer->status === CandidateOfferStatus::Accepted
            && $offer->reservation_status === 'reserved'
            && $offer->hired_employee_id === null;
    }
}
