<?php

namespace Modules\Audit\Application\Commands;

final readonly class CreateAuditExportCommand
{
    /** @param array<string, string|int|null> $filters */
    public function __construct(public int $companyId, public int $actorId, public string $format, public array $filters, public string $idempotencyKey) {}
}
