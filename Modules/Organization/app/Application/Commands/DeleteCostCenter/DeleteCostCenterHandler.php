<?php

namespace Modules\Organization\Application\Commands\DeleteCostCenter;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\CostCenterDeleted;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class DeleteCostCenterHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    public function handle(DeleteCostCenterCommand $c): void
    {
        $this->repo->deleteCostCenter($c->companyId, $c->id);
        $this->events->dispatch(new CostCenterDeleted($c->companyId, ['id' => $c->id], $c->actorId));
    }
}
