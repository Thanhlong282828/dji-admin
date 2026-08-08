<?php

namespace Modules\Payroll\Application\Queries\DownloadPayslip;

use Modules\Payroll\Domain\Services\PayslipGenerationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadPayslipHandler
{
    public function __construct(private PayslipGenerationService $service) {}

    public function handle(DownloadPayslipQuery $q): StreamedResponse
    {
        return $this->service->download($q->companyId, $q->payslipId, $q->actorId, $q->employeeOwner);
    }
}
