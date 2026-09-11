<?php

namespace Modules\Document\Application\Commands;

use Modules\Document\Application\Services\EnterpriseDocumentService;

final readonly class CreateDocumentHandler
{
    public function __construct(private EnterpriseDocumentService $service) {}

    public function handle(CreateDocumentCommand $command): int
    {
        return $this->service->create($command->data, $command->file, $command->actorId);
    }
}
