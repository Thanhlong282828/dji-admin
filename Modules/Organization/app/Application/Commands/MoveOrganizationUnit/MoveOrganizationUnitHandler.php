<?php

namespace Modules\Organization\Application\Commands\MoveOrganizationUnit;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\OrganizationUnitMoved;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class MoveOrganizationUnitHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(MoveOrganizationUnitCommand $c): array
    {
        $r = $this->repo->moveUnit($c->companyId, $c->id, $c->parentId);
        $this->events->dispatch(new OrganizationUnitMoved($c->companyId, $r, $c->actorId));

        return $r;
    }
}
