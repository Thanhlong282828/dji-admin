<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AccountingViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $detail
     * @param  array<string, array<int, array<string, mixed>>>  $options
     */
    public function __construct(public array $items = [], public ?array $detail = null, public array $options = [], public int $page = 1, public bool $hasMore = false) {}
}
