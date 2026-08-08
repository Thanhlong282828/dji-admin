<?php

namespace Modules\Payroll\Application\Commands\AssignSalaryComponent;

use Modules\Payroll\Domain\Models\EmployeeSalaryComponentAssignment;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;

final readonly class AssignSalaryComponentHandler
{
    public function __construct(private PayrollConfigurationService $service) {}

    public function handle(AssignSalaryComponentCommand $c): EmployeeSalaryComponentAssignment
    {
        return $this->service->assign($c->companyId, $c->data, $c->actorId);
    }
}
