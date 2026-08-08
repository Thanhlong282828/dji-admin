<?php

namespace Modules\Recruitment\Application\Commands\RemoveInterviewer;

final readonly class RemoveInterviewerCommand
{
    public function __construct(public int $companyId, public int $interviewId, public int $userId, public int $actorId) {}
}
