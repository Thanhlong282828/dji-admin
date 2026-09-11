<?php

namespace Modules\Document\Application\Commands;

final readonly class ArchiveDocumentCommand
{
    public function __construct(public int $companyId, public int $documentId, public int $actorId) {}
}
