<?php

namespace Modules\Project\Application\Commands;

final readonly class SubmitProjectTimesheetCommand
{
    public function __construct(public int $companyId, public int $projectId, public int $timesheetId, public int $actorId) {}
}
