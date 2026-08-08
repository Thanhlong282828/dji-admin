<?php

namespace Modules\Payroll\Application\Commands\CreatePaymentBatch;

use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;

final readonly class CreatePaymentBatchHandler
{
    public function __construct(private PayrollPaymentBatchService $service) {}

    public function handle(CreatePaymentBatchCommand $c): int
    {
        return $this->service->create($c->companyId, $c->runId, $c->actorId, $c->idempotencyKey);
    }
}
