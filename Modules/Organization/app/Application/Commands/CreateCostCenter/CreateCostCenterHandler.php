<?php

namespace Modules\Organization\Application\Commands\CreateCostCenter;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\CostCenterCreated;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class CreateCostCenterHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(CreateCostCenterCommand $c): array
    {
        $r = $this->repo->createCostCenter($c->companyId, $c->data);
        $this->events->dispatch(new CostCenterCreated($c->companyId, $r, $c->actorId));

        return $r;
    }
}
