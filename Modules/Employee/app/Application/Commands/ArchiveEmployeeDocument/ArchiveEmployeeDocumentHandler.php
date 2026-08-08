<?php

namespace Modules\Employee\Application\Commands\ArchiveEmployeeDocument;

use Modules\Employee\Domain\Services\EmployeeDocumentGovernanceService;

final readonly class ArchiveEmployeeDocumentHandler
{
    public function __construct(private EmployeeDocumentGovernanceService $service) {}

    public function handle(ArchiveEmployeeDocumentCommand $command): int
    {
        return $this->service->archive($command->companyId, $command->documentId, $command->actorId, $command->idempotencyKey, $command->retentionDate);
    }
}
