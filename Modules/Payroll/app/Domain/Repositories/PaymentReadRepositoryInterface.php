<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\PaymentAccountViewModel;
use Modules\Payroll\Application\DTOs\PaymentBatchViewModel;

interface PaymentReadRepositoryInterface
{
    /** @param array{search:string,status:string,page:int} $filters */
    public function batches(int $companyId, array $filters): PaymentBatchViewModel;

    public function batch(int $companyId, int $batchId): PaymentBatchViewModel;

    public function accounts(int $companyId, ?int $employeeId = null): PaymentAccountViewModel;
}
