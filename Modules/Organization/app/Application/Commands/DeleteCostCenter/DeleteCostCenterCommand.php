<?php

namespace Modules\Organization\Application\Commands\DeleteCostCenter;

final readonly class DeleteCostCenterCommand
{
    public function __construct(public int $companyId, public int $id, public int $actorId) {}
}
