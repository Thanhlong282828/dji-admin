<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferExport;

final readonly class GetCandidateOfferExportQuery
{
    public function __construct(public int $companyId, public bool $includeSalary) {}
}
