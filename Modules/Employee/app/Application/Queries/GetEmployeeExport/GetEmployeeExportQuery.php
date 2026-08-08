<?php

namespace Modules\Employee\Application\Queries\GetEmployeeExport;

final readonly class GetEmployeeExportQuery
{
    /** @param array<string, mixed> $filters */
    public function __construct(public int $companyId, public array $filters) {}
}
