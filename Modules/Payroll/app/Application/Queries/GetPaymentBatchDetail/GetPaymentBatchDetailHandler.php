<?php

namespace Modules\Payroll\Application\Queries\GetPaymentBatchDetail;

use Modules\Payroll\Application\DTOs\PaymentBatchViewModel;
use Modules\Payroll\Domain\Repositories\PaymentReadRepositoryInterface;

final readonly class GetPaymentBatchDetailHandler
{
    public function __construct(private PaymentReadRepositoryInterface $repository) {}

    public function handle(GetPaymentBatchDetailQuery $q): PaymentBatchViewModel
    {
        return $this->repository->batch($q->companyId, $q->batchId);
    }
}
