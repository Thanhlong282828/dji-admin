<?php

namespace Modules\Organization\Application\Commands\UpdateCostCenter;

final readonly class UpdateCostCenterCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $id, public array $data, public int $actorId) {}
}
