<?php

namespace Modules\Payroll\Application\Queries\DownloadGovernmentDocument;

use Modules\Payroll\Application\DTOs\GovernmentFilingDownloadData;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;

final readonly class DownloadGovernmentDocumentHandler
{
    public function __construct(private GovernmentTaxFilingService $service) {}

    public function handle(DownloadGovernmentDocumentQuery $q): GovernmentFilingDownloadData
    {
        return $this->service->download($q->companyId, $q->filingId, $q->documentId, $q->actorId);
    }
}
