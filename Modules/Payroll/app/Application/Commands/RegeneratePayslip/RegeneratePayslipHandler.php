<?php

namespace Modules\Payroll\Application\Commands\RegeneratePayslip;

use Modules\Payroll\Domain\Services\PayslipGenerationService;

final readonly class RegeneratePayslipHandler
{
    public function __construct(private PayslipGenerationService $service) {}

    public function handle(RegeneratePayslipCommand $c): void
    {
        $this->service->regenerate($c->companyId, $c->payslipId, $c->actorId);
    }
}
