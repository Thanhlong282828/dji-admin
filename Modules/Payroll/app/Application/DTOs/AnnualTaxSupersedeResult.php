<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxSupersedeResult
{
    public function __construct(public int $oldReconciliationId, public int $newReconciliationId, public int $newVersion) {}
}
