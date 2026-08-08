<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollAdjustment;

use Modules\Payroll\Domain\Services\PayrollAdjustmentManagementService;

final readonly class CreatePayrollAdjustmentHandler
{
    public function __construct(private PayrollAdjustmentManagementService $service) {}

    public function handle(CreatePayrollAdjustmentCommand $c): int
    {
        return $this->service->create($c);
    }
}
