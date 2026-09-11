<?php

namespace Modules\Project\Application\Commands;

final readonly class CreateProjectCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public array $data, public int $actorId) {}
}
