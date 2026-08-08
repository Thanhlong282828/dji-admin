<?php

namespace Modules\Recruitment\Application\Commands\SubmitInterviewEvaluation;

use Modules\Recruitment\Application\DTOs\InterviewEvaluationData;

final readonly class SubmitInterviewEvaluationCommand
{
    public function __construct(public int $companyId, public int $interviewId, public int $interviewerId, public InterviewEvaluationData $data) {}
}
