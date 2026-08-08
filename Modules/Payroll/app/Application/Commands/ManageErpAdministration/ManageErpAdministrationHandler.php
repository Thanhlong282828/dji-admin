<?php

namespace Modules\Payroll\Application\Commands\ManageErpAdministration;

use Modules\Payroll\Domain\Services\ErpAdministrationService;

final readonly class ManageErpAdministrationHandler
{
    public function __construct(private ErpAdministrationService $service) {}

    public function handle(ManageErpAdministrationCommand $command): int
    {
        return $this->service->execute($command->data);
    }
}
