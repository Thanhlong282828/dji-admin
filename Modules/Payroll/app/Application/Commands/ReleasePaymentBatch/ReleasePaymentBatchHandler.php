<?php

namespace Modules\Payroll\Application\Commands\ReleasePaymentBatch;

use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;

final readonly class ReleasePaymentBatchHandler
{
    public function __construct(private PayrollPaymentBatchService $service) {}

    public function handle(ReleasePaymentBatchCommand $c): void
    {
        $this->service->release($c->companyId, $c->batchId, $c->actorId);
    }
}
