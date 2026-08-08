<?php

namespace Modules\Payroll\Application\Commands\RejectPaymentBatch;

final readonly class RejectPaymentBatchCommand
{
    public function __construct(public int $companyId, public int $batchId, public int $actorId, public string $reason) {}
}
