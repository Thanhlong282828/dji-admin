<?php

namespace Modules\Payroll\Application\Queries\GetPaymentBatchList;

use Modules\Payroll\Application\DTOs\PaymentBatchViewModel;
use Modules\Payroll\Domain\Repositories\PaymentReadRepositoryInterface;

final readonly class GetPaymentBatchListHandler
{
    public function __construct(private PaymentReadRepositoryInterface $repository) {}

    public function handle(GetPaymentBatchListQuery $q): PaymentBatchViewModel
    {
        return $this->repository->batches($q->companyId, $q->filters);
    }
}
