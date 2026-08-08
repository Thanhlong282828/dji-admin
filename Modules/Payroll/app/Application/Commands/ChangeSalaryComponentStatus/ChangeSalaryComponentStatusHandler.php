<?php

namespace Modules\Payroll\Application\Commands\ChangeSalaryComponentStatus;

use Modules\Payroll\Domain\Models\SalaryComponent;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;

final readonly class ChangeSalaryComponentStatusHandler
{
    public function __construct(private PayrollConfigurationService $service) {}

    public function handle(ChangeSalaryComponentStatusCommand $c): SalaryComponent
    {
        return $this->service->statusComponent($c->companyId, $c->componentId, $c->status, $c->expectedVersion, $c->actorId);
    }
}
