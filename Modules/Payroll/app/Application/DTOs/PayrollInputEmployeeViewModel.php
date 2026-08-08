<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollInputEmployeeViewModel
{
    /**
     * @param  list<array<string,int|string|null>>  $items
     * @param  array<string,int|string|null>|null  $detail
     * @param  list<array<string,int|string|bool|null>>  $sources
     * @param  list<array<string,int|string|bool|null>>  $issues
     */
    public function __construct(public array $items = [], public ?array $detail = null, public array $sources = [], public array $issues = [], public int $page = 1, public bool $hasMore = false) {}
}
