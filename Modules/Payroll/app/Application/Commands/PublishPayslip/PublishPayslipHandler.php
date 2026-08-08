<?php

namespace Modules\Payroll\Application\Commands\PublishPayslip;

use Modules\Payroll\Domain\Services\PayslipGenerationService;

final readonly class PublishPayslipHandler
{
    public function __construct(private PayslipGenerationService $service) {}

    public function handle(PublishPayslipCommand $c): void
    {
        $this->service->publish($c->companyId, $c->payslipId, $c->actorId);
    }
}
