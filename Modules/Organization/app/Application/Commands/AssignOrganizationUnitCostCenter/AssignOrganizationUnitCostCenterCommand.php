<?php

namespace Modules\Organization\Application\Commands\AssignOrganizationUnitCostCenter;

final readonly class AssignOrganizationUnitCostCenterCommand
{
    public function __construct(
        public int $companyId,
        public int $unitId,
        public ?int $costCenterId,
        public int $actorId,
    ) {}
}
