<?php

namespace Modules\Payroll\Application\Queries\GetErpExportList;

final readonly class GetErpExportListQuery
{
    public function __construct(public int $companyId, public string $search = '', public string $status = '') {}
}
