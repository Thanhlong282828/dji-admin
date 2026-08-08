<?php

namespace Modules\Payroll\Application\Commands\ValidatePaymentBatch;

final readonly class ValidatePaymentBatchCommand
{
    public function __construct(public int $companyId, public int $batchId, public int $actorId) {}
}
