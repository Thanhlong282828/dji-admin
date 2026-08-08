<?php

namespace Modules\Organization\Application\Commands\UpdateCostCenter;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\CostCenterUpdated;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class UpdateCostCenterHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(UpdateCostCenterCommand $c): array
    {
        $r = $this->repo->updateCostCenter($c->companyId, $c->id, $c->data);
        $this->events->dispatch(new CostCenterUpdated($c->companyId, $r, $c->actorId));

        return $r;
    }
}
