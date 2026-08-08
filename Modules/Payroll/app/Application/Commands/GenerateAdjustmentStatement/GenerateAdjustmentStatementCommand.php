<?php

namespace Modules\Payroll\Application\Commands\GenerateAdjustmentStatement;

final readonly class GenerateAdjustmentStatementCommand
{
    public function __construct(public int $companyId, public int $adjustmentId, public int $actorId) {}
}
