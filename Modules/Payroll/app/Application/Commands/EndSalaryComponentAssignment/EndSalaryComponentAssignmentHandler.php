<?php

namespace Modules\Payroll\Application\Commands\EndSalaryComponentAssignment;

use Modules\Payroll\Domain\Models\EmployeeSalaryComponentAssignment;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;

final readonly class EndSalaryComponentAssignmentHandler
{
    public function __construct(private PayrollConfigurationService $service) {}

    public function handle(EndSalaryComponentAssignmentCommand $c): EmployeeSalaryComponentAssignment
    {
        return $this->service->endAssignment($c->companyId, $c->assignmentId, $c->effectiveTo, $c->expectedVersion, $c->actorId);
    }
}
