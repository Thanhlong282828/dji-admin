<?php

namespace Modules\Payroll\Application\Commands\SavePaymentAccount;

use Modules\Payroll\Domain\Services\EmployeePaymentAccountService;

final readonly class SavePaymentAccountHandler
{
    public function __construct(private EmployeePaymentAccountService $service) {}

    public function handle(SavePaymentAccountCommand $c): int
    {
        return $this->service->save($c->companyId, $c->accountId, $c->data, $c->actorId);
    }
}
