<?php

namespace Modules\Integration\Application\Commands;

final readonly class CreateIntegrationConnectionCommand
{
    /** @param array<string,mixed> $data */
    public function __construct(public int $companyId, public int $actorId, public array $data) {}
}
