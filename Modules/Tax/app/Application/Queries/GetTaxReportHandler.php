<?php

namespace Modules\Tax\Application\Queries;

use Modules\Tax\Application\Services\TaxReportReadService;

final readonly class GetTaxReportHandler
{
    public function __construct(private TaxReportReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetTaxReportQuery $query): array
    {
        return $this->service->read(
            $query->companyId,
            $query->branchId,
            $query->from,
            $query->to,
            $query->currency,
            $query->actor,
        );
    }
}
