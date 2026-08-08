<?php

namespace Modules\Organization\Application\Commands\CreateCostCenter;

final readonly class CreateCostCenterCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public array $data, public int $actorId) {}
}
