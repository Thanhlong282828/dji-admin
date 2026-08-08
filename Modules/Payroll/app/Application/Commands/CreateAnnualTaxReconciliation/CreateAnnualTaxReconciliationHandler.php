<?php

namespace Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation;

use Modules\Payroll\Application\DTOs\AnnualTaxDraftData;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;

final readonly class CreateAnnualTaxReconciliationHandler
{
    public function __construct(private PayrollAnnualTaxReconciliationService $service) {}

    public function handle(CreateAnnualTaxReconciliationCommand $command): AnnualTaxDraftData
    {
        return $this->service->create($command);
    }
}
