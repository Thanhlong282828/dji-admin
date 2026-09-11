<?php

namespace Modules\Document\Application\Commands;

use Modules\Document\Application\Services\EnterpriseDocumentService;

final readonly class ArchiveDocumentHandler
{
    public function __construct(private EnterpriseDocumentService $service) {}

    public function handle(ArchiveDocumentCommand $command): void
    {
        $this->service->archive($command->companyId, $command->documentId, $command->actorId);
    }
}
