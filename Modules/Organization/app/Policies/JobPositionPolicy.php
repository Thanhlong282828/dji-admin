<?php

namespace Modules\Organization\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class JobPositionPolicy
{
    public function __construct(private TenantAccessService $access) {}

    public function view(User $u, int $c): bool
    {
        return $u->can('job-position.view') && $this->access->canAccess($u, $c);
    }

    public function create(User $u, int $c): bool
    {
        return $u->can('job-position.create') && $this->access->canAccess($u, $c);
    }

    public function update(User $u, int $c): bool
    {
        return $u->can('job-position.update') && $this->access->canAccess($u, $c);
    }

    public function delete(User $u, int $c): bool
    {
        return $u->can('job-position.delete') && $this->access->canAccess($u, $c);
    }
}
