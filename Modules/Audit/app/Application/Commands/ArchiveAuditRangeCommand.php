<?php

namespace Modules\Audit\Application\Commands;

final readonly class ArchiveAuditRangeCommand
{
    public function __construct(public int $companyId, public int $actorId, public string $rangeStart, public string $rangeEnd, public string $idempotencyKey) {}
}
