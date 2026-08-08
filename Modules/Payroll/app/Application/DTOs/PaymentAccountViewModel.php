<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PaymentAccountViewModel
{
    /**
     * @param  list<array<string, int|string|bool|null>>  $items
     * @param  array<string, int|string|bool|null>|null  $detail
     */
    public function __construct(public array $items = [], public ?array $detail = null) {}
}
