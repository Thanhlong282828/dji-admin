<?php

namespace Modules\Audit\Application\Commands;

use Modules\Audit\Domain\Services\AuditExportService;

final readonly class CreateAuditExportHandler
{
    public function __construct(private AuditExportService $service) {}

    public function handle(CreateAuditExportCommand $command): int
    {
        return $this->service->create($command);
    }
}
