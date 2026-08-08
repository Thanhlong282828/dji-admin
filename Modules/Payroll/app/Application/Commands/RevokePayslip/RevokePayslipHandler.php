<?php

namespace Modules\Payroll\Application\Commands\RevokePayslip;

use Modules\Payroll\Domain\Services\PayslipGenerationService;

final readonly class RevokePayslipHandler
{
    public function __construct(private PayslipGenerationService $service) {}

    public function handle(RevokePayslipCommand $c): void
    {
        $this->service->revoke($c->companyId, $c->payslipId, $c->actorId, $c->reason);
    }
}
