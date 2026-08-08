<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollAdjustmentViewModel
{
    /** @param array<string,mixed> $data */
    public function __construct(public string $view, public array $data) {}
}
