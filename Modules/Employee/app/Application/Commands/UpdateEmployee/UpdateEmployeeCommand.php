<?php

namespace Modules\Employee\Application\Commands\UpdateEmployee;

final readonly class UpdateEmployeeCommand
{
    /** @param array<string, mixed> $data */
    public function __construct(public int $companyId, public int $id, public array $data, public int $actorId) {}
}
