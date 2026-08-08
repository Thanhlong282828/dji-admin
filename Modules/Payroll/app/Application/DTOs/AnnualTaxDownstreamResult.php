<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxDownstreamResult
{
    public function __construct(public int $reconciliationId, public string $type, public string $status, public ?int $resourceId, public bool $existing) {}
}
