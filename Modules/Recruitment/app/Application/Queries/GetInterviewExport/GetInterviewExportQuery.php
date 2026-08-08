<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewExport;

final readonly class GetInterviewExportQuery
{
    public function __construct(public int $companyId) {}
}
