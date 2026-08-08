<?php

namespace Modules\Attendance\Application\Queries;

final readonly class GetScheduleRuleEditorQuery
{
    public function __construct(public string $type, public int $companyId, public int $parentId) {}
}
