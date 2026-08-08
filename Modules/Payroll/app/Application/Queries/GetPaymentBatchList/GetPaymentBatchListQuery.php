<?php

namespace Modules\Payroll\Application\Queries\GetPaymentBatchList;

final readonly class GetPaymentBatchListQuery
{
    /** @param array{search:string,status:string,page:int} $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
