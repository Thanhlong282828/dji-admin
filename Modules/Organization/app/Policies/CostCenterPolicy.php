<?php

namespace Modules\Organization\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class CostCenterPolicy
{
    public function __construct(private TenantAccessService $access) {}

    public function view(User $u, int $companyId): bool
    {
        return $u->can('cost-center.view') && $this->access->canAccess($u, $companyId);
    }

    public function create(User $u, int $companyId): bool
    {
        return $u->can('cost-center.create') && $this->access->canAccess($u, $companyId);
    }

    public function update(User $u, int $companyId): bool
    {
        return $u->can('cost-center.update') && $this->access->canAccess($u, $companyId);
    }

    public function delete(User $u, int $companyId): bool
    {
        return $u->can('cost-center.delete') && $this->access->canAccess($u, $companyId);
    }
}
