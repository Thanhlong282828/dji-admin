<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class GovernmentFilingResult
{
    public function __construct(public int $filingId, public string $status, public int $version) {}
}
