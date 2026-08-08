<?php

namespace Modules\Organization\Application\Commands\DeleteJobPosition;

final readonly class DeleteJobPositionCommand
{
    public function __construct(public int $companyId, public int $id, public int $actorId) {}
}
