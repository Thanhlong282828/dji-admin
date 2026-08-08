<?php

namespace Modules\Attendance\Domain\Repositories;

use Modules\Attendance\Application\DTOs\ScheduleRuleEditorViewModel;

interface ScheduleRuleReadRepositoryInterface
{
    public function editor(string $type, int $companyId, int $parentId): ScheduleRuleEditorViewModel;
}
