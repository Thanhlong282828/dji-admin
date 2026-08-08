<?php

namespace Modules\Payroll\Application\Commands\SavePaymentAccount;

final readonly class SavePaymentAccountCommand
{
    /** @param array{employee_id:int,payment_method:string,bank_code:?string,bank_name:?string,branch_code:?string,account_number:?string,account_holder_name:string,currency:string,is_primary:bool,effective_from:string,effective_to:?string,status:string} $data */
    public function __construct(public int $companyId, public ?int $accountId, public array $data, public int $actorId) {}
}
