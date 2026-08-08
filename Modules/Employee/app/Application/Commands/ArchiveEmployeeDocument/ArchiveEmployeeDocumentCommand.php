<?php

namespace Modules\Employee\Application\Commands\ArchiveEmployeeDocument;

final readonly class ArchiveEmployeeDocumentCommand
{
    public function __construct(public int $companyId, public int $documentId, public int $actorId, public string $idempotencyKey, public ?string $retentionDate) {}
}
