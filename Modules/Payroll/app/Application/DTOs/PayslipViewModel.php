<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayslipViewModel
{
    /**
     * @param  list<array<string, int|string|null>>  $items
     * @param  array<string, mixed>|null  $detail
     */
    public function __construct(public array $items = [], public ?array $detail = null, public int $page = 1, public bool $hasMore = false) {}
}
