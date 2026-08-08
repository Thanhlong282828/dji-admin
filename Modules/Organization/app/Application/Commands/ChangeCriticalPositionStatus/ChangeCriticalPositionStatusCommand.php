<?php

namespace Modules\Organization\Application\Commands\ChangeCriticalPositionStatus;

final readonly class ChangeCriticalPositionStatusCommand
{
    public function __construct(public int $companyId, public int $id, public bool $critical, public int $actorId) {}
}
