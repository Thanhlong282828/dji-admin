<?php

namespace Modules\Payroll\Application\Commands\VerifyPaymentAccount;

final readonly class VerifyPaymentAccountCommand
{
    public function __construct(public int $companyId, public int $accountId, public int $actorId) {}
}
