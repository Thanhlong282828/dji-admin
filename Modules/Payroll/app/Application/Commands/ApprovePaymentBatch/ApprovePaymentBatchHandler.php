<?php

namespace Modules\Payroll\Application\Commands\ApprovePaymentBatch;

use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;

final readonly class ApprovePaymentBatchHandler
{
    public function __construct(private PayrollPaymentBatchService $service) {}

    public function handle(ApprovePaymentBatchCommand $c): void
    {
        $this->service->approve($c->companyId, $c->batchId, $c->actorId);
    }
}
