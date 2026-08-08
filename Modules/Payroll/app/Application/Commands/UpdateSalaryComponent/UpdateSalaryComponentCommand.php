<?php

namespace Modules\Payroll\Application\Commands\UpdateSalaryComponent;

final readonly class UpdateSalaryComponentCommand
{
    /** @param array<string,bool|int|string|null> $data */
    public function __construct(public int $companyId, public int $componentId, public array $data, public int $expectedVersion, public int $actorId) {}
}
