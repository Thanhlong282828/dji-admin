<?php

namespace Modules\Payroll\Application\Commands\RejectPaymentBatch;

use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;

final readonly class RejectPaymentBatchHandler
{
    public function __construct(private PayrollPaymentBatchService $service) {}

    public function handle(RejectPaymentBatchCommand $c): void
    {
        $this->service->reject($c->companyId, $c->batchId, $c->actorId, $c->reason);
    }
}
