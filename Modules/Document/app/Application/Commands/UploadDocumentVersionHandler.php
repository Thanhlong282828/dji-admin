<?php

namespace Modules\Document\Application\Commands;

use Modules\Document\Application\Services\EnterpriseDocumentService;

final readonly class UploadDocumentVersionHandler
{
    public function __construct(private EnterpriseDocumentService $service) {}

    public function handle(UploadDocumentVersionCommand $command): int
    {
        return $this->service->upload($command->companyId, $command->documentId, $command->file, $command->actorId);
    }
}
