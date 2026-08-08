<?php

namespace Modules\Payroll\Application\Queries\DownloadPaymentBatchFile;

final readonly class DownloadPaymentBatchFileQuery
{
    public function __construct(public int $companyId, public int $batchId, public int $actorId) {}
}
