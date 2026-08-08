<?php

namespace Modules\Employee\Application\Commands\RestoreEmployee;

final readonly class RestoreEmployeeCommand
{
    public function __construct(public int $companyId, public int $id, public int $actorId) {}
}
