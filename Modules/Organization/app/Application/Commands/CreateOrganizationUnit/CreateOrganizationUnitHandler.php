<?php

namespace Modules\Organization\Application\Commands\CreateOrganizationUnit;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\OrganizationUnitCreated;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class CreateOrganizationUnitHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(CreateOrganizationUnitCommand $c): array
    {
        $r = $this->repo->createUnit($c->companyId, $c->data);
        $this->events->dispatch(new OrganizationUnitCreated($c->companyId, $r, $c->actorId));

        return $r;
    }
}
