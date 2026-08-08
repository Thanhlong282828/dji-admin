<?php

namespace Modules\Attendance\Application\Queries;

use Modules\Attendance\Application\DTOs\ScheduleRuleEditorViewModel;
use Modules\Attendance\Domain\Repositories\ScheduleRuleReadRepositoryInterface;

final readonly class GetScheduleRuleEditorHandler
{
    public function __construct(private ScheduleRuleReadRepositoryInterface $repository) {}

    public function handle(GetScheduleRuleEditorQuery $query): ScheduleRuleEditorViewModel
    {
        return $this->repository->editor($query->type, $query->companyId, $query->parentId);
    }
}
