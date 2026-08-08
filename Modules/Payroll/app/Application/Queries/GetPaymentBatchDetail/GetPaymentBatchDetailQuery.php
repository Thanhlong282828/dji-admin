<?php

namespace Modules\Payroll\Application\Queries\GetPaymentBatchDetail;

final readonly class GetPaymentBatchDetailQuery
{
    public function __construct(public int $companyId, public int $batchId) {}
}
