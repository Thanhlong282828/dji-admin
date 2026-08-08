<?php

namespace Modules\Payroll\Application\Commands\ManageErpAdministration;

use Modules\Payroll\Application\DTOs\ErpAdministrationData;

final readonly class ManageErpAdministrationCommand
{
    public function __construct(public ErpAdministrationData $data) {}
}
