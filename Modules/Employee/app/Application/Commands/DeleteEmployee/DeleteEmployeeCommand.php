<?php

namespace Modules\Employee\Application\Commands\DeleteEmployee;

final readonly class DeleteEmployeeCommand
{
    public function __construct(public int $companyId, public int $id, public int $actorId) {}
}
