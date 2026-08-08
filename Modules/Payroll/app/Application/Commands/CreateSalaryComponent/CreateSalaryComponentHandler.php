<?php

namespace Modules\Payroll\Application\Commands\CreateSalaryComponent;

use Modules\Payroll\Domain\Models\SalaryComponent;
use Modules\Payroll\Domain\Services\PayrollConfigurationService;

final readonly class CreateSalaryComponentHandler
{
    public function __construct(private PayrollConfigurationService $service) {}

    public function handle(CreateSalaryComponentCommand $c): SalaryComponent
    {
        return $this->service->createComponent($c->companyId, $c->data, $c->actorId);
    }
}
