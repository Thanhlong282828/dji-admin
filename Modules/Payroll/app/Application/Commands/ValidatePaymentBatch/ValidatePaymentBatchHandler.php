<?php

namespace Modules\Payroll\Application\Commands\ValidatePaymentBatch;

use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;

final readonly class ValidatePaymentBatchHandler
{
    public function __construct(private PayrollPaymentBatchService $service) {}

    public function handle(ValidatePaymentBatchCommand $c): void
    {
        $this->service->validate($c->companyId, $c->batchId, $c->actorId);
    }
}
