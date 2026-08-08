<?php

namespace Modules\Payroll\Application\Commands\VerifyPaymentAccount;

use Modules\Payroll\Domain\Services\EmployeePaymentAccountService;

final readonly class VerifyPaymentAccountHandler
{
    public function __construct(private EmployeePaymentAccountService $service) {}

    public function handle(VerifyPaymentAccountCommand $c): void
    {
        $this->service->verify($c->companyId, $c->accountId, $c->actorId);
    }
}
