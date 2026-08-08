<?php

namespace Modules\Payroll\Application\Queries\GetErpExportDetail;

final readonly class GetErpExportDetailQuery
{
    public function __construct(public int $companyId, public int $exportId) {}
}
