<?php

namespace Modules\Payroll\Application\Queries\GetErpExportCreateOptions;

final readonly class GetErpExportCreateOptionsQuery
{
    public function __construct(public int $companyId) {}
}
