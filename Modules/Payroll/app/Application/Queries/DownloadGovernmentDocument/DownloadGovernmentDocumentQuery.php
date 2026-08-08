<?php

namespace Modules\Payroll\Application\Queries\DownloadGovernmentDocument;

final readonly class DownloadGovernmentDocumentQuery
{
    public function __construct(public int $companyId, public int $filingId, public int $documentId, public int $actorId) {}
}
