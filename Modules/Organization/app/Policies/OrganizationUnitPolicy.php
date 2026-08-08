<?php

namespace Modules\Organization\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class OrganizationUnitPolicy
{
    public function __construct(private TenantAccessService $access) {}

    public function view(User $u, int $c): bool
    {
        return $u->can('organization-unit.view') && $this->access->canAccess($u, $c);
    }

    public function create(User $u, int $c): bool
    {
        return $u->can('organization-unit.create') && $this->access->canAccess($u, $c);
    }

    public function update(User $u, int $c): bool
    {
        return $u->can('organization-unit.update') && $this->access->canAccess($u, $c);
    }

    public function move(User $u, int $c): bool
    {
        return $u->can('organization-unit.move') && $this->access->canAccess($u, $c);
    }

    public function delete(User $u, int $c): bool
    {
        return $u->can('organization-unit.delete') && $this->access->canAccess($u, $c);
    }
}
