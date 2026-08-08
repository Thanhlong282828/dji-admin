<?php

namespace Modules\Payroll\Application\Commands\ReleasePaymentBatch;

final readonly class ReleasePaymentBatchCommand
{
    public function __construct(public int $companyId, public int $batchId, public int $actorId) {}
}
