<?php

namespace Modules\Payroll\Application\Queries\GetPayrollAdjustment;

final readonly class GetPayrollAdjustmentQuery
{
    /** @param array{search:string,status:string,page:int} $filters */
    public function __construct(public int $companyId, public string $view, public ?int $id = null, public array $filters = ['search' => '', 'status' => '', 'page' => 1]) {}
}
