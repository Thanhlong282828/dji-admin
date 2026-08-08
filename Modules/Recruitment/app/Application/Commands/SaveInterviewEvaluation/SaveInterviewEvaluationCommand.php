<?php

namespace Modules\Recruitment\Application\Commands\SaveInterviewEvaluation;

use Modules\Recruitment\Application\DTOs\InterviewEvaluationData;

final readonly class SaveInterviewEvaluationCommand
{
    public function __construct(public int $companyId, public int $interviewId, public int $interviewerId, public InterviewEvaluationData $data, public bool $submit) {}
}
