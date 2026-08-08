<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class GovernmentFilingViewModel
{
    /**
     * @param  list<array<string, int|string|null>>  $items
     * @param  array<string, mixed>  $detail
     * @param  list<array<string, int|string|null>>  $history
     */
    public function __construct(public array $items = [], public array $detail = [], public array $history = []) {}
}
