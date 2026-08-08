<?php

namespace Modules\Organization\Application\Commands\UpdateJobPosition;

final readonly class UpdateJobPositionCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $id, public array $data, public int $actorId) {}
}
