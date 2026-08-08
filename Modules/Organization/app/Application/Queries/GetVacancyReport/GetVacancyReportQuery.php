<?php

namespace Modules\Organization\Application\Queries\GetVacancyReport;

final readonly class GetVacancyReportQuery
{
    public function __construct(public int $companyId) {}
}
