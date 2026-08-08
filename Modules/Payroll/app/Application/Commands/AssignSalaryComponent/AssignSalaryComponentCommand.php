<?php

namespace Modules\Payroll\Application\Commands\AssignSalaryComponent;

final readonly class AssignSalaryComponentCommand
{
    /** @param array<string,int|string|null> $data */
    public function __construct(public int $companyId, public array $data, public int $actorId) {}
}
