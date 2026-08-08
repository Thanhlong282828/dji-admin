<?php

namespace Modules\Payroll\Application\Queries\GetSubmissionHistory;

final readonly class GetSubmissionHistoryQuery
{
    public function __construct(public int $companyId, public int $filingId) {}
}
