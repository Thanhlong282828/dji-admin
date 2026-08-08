<?php

namespace Modules\Attendance\Application\Commands;

final readonly class ProcessAttendanceWorkflowCommand
{
    /** @param array<string, int|string|null> $data */
    public function __construct(public string $action, public int $companyId, public int $subjectId, public int $actorId, public int $expectedVersion, public array $data = []) {}
}
