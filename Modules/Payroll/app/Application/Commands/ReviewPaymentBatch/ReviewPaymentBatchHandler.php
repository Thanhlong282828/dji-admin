<?php

namespace Modules\Payroll\Application\Commands\ReviewPaymentBatch;

use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;

final readonly class ReviewPaymentBatchHandler
{
    public function __construct(private PayrollPaymentBatchService $service) {}

    public function handle(ReviewPaymentBatchCommand $c): void
    {
        $this->service->review($c->companyId, $c->batchId, $c->actorId);
    }
}
