<?php

namespace Modules\Payroll\Application\Queries\DownloadPaymentBatchFile;

use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadPaymentBatchFileHandler
{
    public function __construct(private PayrollPaymentBatchService $service) {}

    public function handle(DownloadPaymentBatchFileQuery $q): StreamedResponse
    {
        return $this->service->download($q->companyId, $q->batchId, $q->actorId);
    }
}
