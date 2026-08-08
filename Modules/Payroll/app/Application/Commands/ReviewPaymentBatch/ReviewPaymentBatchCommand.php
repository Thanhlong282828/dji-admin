<?php

namespace Modules\Payroll\Application\Commands\ReviewPaymentBatch;

final readonly class ReviewPaymentBatchCommand
{
    public function __construct(public int $companyId, public int $batchId, public int $actorId) {}
}
