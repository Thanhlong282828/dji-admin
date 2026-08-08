<?php

namespace Modules\Organization\Application\Commands\AssignOrganizationUnitCostCenter;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\OrganizationUnitUpdated;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class AssignOrganizationUnitCostCenterHandler
{
    public function __construct(
        private OrganizationWriteRepositoryInterface $repository,
        private Dispatcher $events,
    ) {}

    /** @return array<string, mixed> */
    public function handle(AssignOrganizationUnitCostCenterCommand $command): array
    {
        $unit = $this->repository->assignUnitCostCenter(
            $command->companyId,
            $command->unitId,
            $command->costCenterId,
        );
        $this->events->dispatch(new OrganizationUnitUpdated(
            $command->companyId,
            $unit,
            $command->actorId,
        ));

        return $unit;
    }
}
