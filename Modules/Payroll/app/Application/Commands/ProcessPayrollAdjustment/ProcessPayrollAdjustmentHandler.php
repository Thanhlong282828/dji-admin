<?php

namespace Modules\Payroll\Application\Commands\ProcessPayrollAdjustment;

use Modules\Payroll\Domain\Services\PayrollAdjustmentManagementService;

final readonly class ProcessPayrollAdjustmentHandler
{
    public function __construct(private PayrollAdjustmentManagementService $service) {}

    public function handle(ProcessPayrollAdjustmentCommand $c): void
    {
        $this->service->process($c);
    }
}
