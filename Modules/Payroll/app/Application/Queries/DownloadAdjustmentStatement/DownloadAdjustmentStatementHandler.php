<?php

namespace Modules\Payroll\Application\Queries\DownloadAdjustmentStatement;

use Modules\Payroll\Domain\Services\PayrollAdjustmentStatementService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadAdjustmentStatementHandler
{
    public function __construct(private PayrollAdjustmentStatementService $service) {}

    public function handle(DownloadAdjustmentStatementQuery $query): StreamedResponse
    {
        return $this->service->download($query->companyId, $query->adjustmentId, $query->actorId);
    }
}
