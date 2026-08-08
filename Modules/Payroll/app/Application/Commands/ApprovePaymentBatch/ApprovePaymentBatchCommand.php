<?php

namespace Modules\Payroll\Application\Commands\ApprovePaymentBatch;

final readonly class ApprovePaymentBatchCommand
{
    public function __construct(public int $companyId, public int $batchId, public int $actorId) {}
}
