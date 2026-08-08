<?php

namespace Modules\Audit\Application\Commands;

use Modules\Audit\Domain\Services\AuditRetentionService;

final readonly class ArchiveAuditRangeHandler
{
    public function __construct(private AuditRetentionService $service) {}

    public function handle(ArchiveAuditRangeCommand $command): int
    {
        return $this->service->archive($command);
    }
}
