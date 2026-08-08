<?php

namespace Modules\Recruitment\Application\Commands\AssignInterviewers;

final readonly class AssignInterviewersCommand
{
    /** @param list<int> $userIds */
    public function __construct(public int $companyId, public int $interviewId, public array $userIds, public int $actorId) {}
}
