<?php

namespace Modules\Attendance\Application\Queries;

final readonly class GetWorkforceManagementQuery
{
    public function __construct(public int $companyId, public string $section, public ?string $search = null) {}
}
