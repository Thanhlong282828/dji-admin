<?php

namespace Modules\Integration\Application\Commands;

final readonly class ReplayDeadLetterCommand
{
    public function __construct(public int $companyId, public int $deadLetterId, public int $actorId, public string $idempotencyKey) {}
}
