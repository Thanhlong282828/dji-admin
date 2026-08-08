<?php

namespace Modules\Payroll\Application\Commands\UpdateSalaryComponent;

use Modules\Payroll\Domain\Models\SalaryComponent;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;

final readonly class UpdateSalaryComponentHandler
{
    public function __construct(private PayrollConfigurationService $service) {}

    public function handle(UpdateSalaryComponentCommand $c): SalaryComponent
    {
        return $this->service->updateComponent($c->companyId, $c->componentId, $c->data, $c->expectedVersion, $c->actorId);
    }
}
