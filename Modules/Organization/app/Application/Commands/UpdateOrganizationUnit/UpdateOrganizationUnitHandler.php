<?php

namespace Modules\Organization\Application\Commands\UpdateOrganizationUnit;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\OrganizationUnitUpdated;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class UpdateOrganizationUnitHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(UpdateOrganizationUnitCommand $c): array
    {
        $r = $this->repo->updateUnit($c->companyId, $c->id, $c->data);
        $this->events->dispatch(new OrganizationUnitUpdated($c->companyId, $r, $c->actorId));

        return $r;
    }
}
