<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class ErpExportViewModel
{
    /**
     * @param  list<array<int|string,int|string|null>>  $items
     * @param  array<int|string,int|string|null|list<array<int|string,int|string|null>>>  $detail
     * @param  list<array<int|string,int|string|null>>  $logs
     * @param  array<string,list<array<int|string,int|string|null>>>  $options
     */
    public function __construct(public array $items = [], public array $detail = [], public array $logs = [], public array $options = []) {}
}
