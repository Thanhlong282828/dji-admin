<?php

namespace Modules\Payroll\Application\Queries\DownloadAnnualTaxStatement;

use Modules\Payroll\Application\DTOs\AnnualTaxDownloadData;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxDeliveryService;

final readonly class DownloadAnnualTaxStatementHandler
{
    public function __construct(private PayrollAnnualTaxDeliveryService $service) {}

    public function handle(DownloadAnnualTaxStatementQuery $query): AnnualTaxDownloadData
    {
        return $this->service->download($query->companyId, $query->reconciliationId, $query->documentId, $query->actorId, $query->ip, $query->userAgent);
    }
}
