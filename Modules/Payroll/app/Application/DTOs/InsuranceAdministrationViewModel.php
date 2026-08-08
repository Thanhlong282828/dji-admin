<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class InsuranceAdministrationViewModel
{
    /**
     * @param  list<array<string,int|string|null>>  $items
     * @param  array<string,mixed>|null  $detail
     * @param  list<array{id:int,code:string,name:string}>  $policies
     */
    public function __construct(public array $items = [], public ?array $detail = null, public array $policies = [], public int $page = 1, public bool $hasMore = false) {}
}
