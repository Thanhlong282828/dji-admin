<?php

namespace Modules\Payroll\Application\Commands\CreateSalaryComponent;

final readonly class CreateSalaryComponentCommand
{
    /** @param array<string,bool|int|string|null> $data */
    public function __construct(public int $companyId, public array $data, public int $actorId) {}
}
