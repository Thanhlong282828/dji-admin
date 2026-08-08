<?php

namespace Modules\Attendance\Application\Commands;

final readonly class ManageScheduleRulesCommand
{
    /** @param array<string, bool|int|string|null|list<array{weekday:int,is_working_day:bool,planned_minutes:int,default_shift_id:int|null}>> $data */
    public function __construct(public string $action, public int $companyId, public int $parentId, public ?int $ruleId, public int $expectedVersion, public int $actorId, public array $data) {}
}
