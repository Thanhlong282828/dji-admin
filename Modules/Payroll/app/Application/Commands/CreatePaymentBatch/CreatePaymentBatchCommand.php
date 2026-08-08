<?php

namespace Modules\Payroll\Application\Commands\CreatePaymentBatch;

final readonly class CreatePaymentBatchCommand
{
    public function __construct(public int $companyId, public int $runId, public int $actorId, public string $idempotencyKey) {}
}
