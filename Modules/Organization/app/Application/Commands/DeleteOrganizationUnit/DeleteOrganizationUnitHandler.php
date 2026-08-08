<?php

namespace Modules\Organization\Application\Commands\DeleteOrganizationUnit;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\OrganizationUnitDeleted;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class DeleteOrganizationUnitHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    public function handle(DeleteOrganizationUnitCommand $c): void
    {
        $this->repo->deleteUnit($c->companyId, $c->id);
        $this->events->dispatch(new OrganizationUnitDeleted($c->companyId, ['id' => $c->id], $c->actorId));
    }
}
