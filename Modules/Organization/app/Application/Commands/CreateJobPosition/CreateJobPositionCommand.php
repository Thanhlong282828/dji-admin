<?php

namespace Modules\Organization\Application\Commands\CreateJobPosition;

final readonly class CreateJobPositionCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public array $data, public int $actorId) {}
}
