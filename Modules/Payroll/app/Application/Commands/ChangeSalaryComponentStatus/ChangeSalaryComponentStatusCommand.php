<?php

namespace Modules\Payroll\Application\Commands\ChangeSalaryComponentStatus;

final readonly class ChangeSalaryComponentStatusCommand
{
    public function __construct(public int $companyId, public int $componentId, public string $status, public int $expectedVersion, public int $actorId) {}
}
