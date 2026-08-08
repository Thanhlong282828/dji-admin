<?php

namespace Modules\Payroll\Application\Queries\DownloadAdjustmentStatement;

final readonly class DownloadAdjustmentStatementQuery
{
    public function __construct(public int $companyId, public int $adjustmentId, public int $actorId) {}
}
