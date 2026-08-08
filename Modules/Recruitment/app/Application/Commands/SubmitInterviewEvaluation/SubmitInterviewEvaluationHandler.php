<?php

namespace Modules\Recruitment\Application\Commands\SubmitInterviewEvaluation;

use Modules\Recruitment\Domain\Models\InterviewEvaluation;
use Modules\Recruitment\Domain\Services\InterviewEvaluationService;

final readonly class SubmitInterviewEvaluationHandler
{
    public function __construct(private InterviewEvaluationService $service) {}

    public function handle(SubmitInterviewEvaluationCommand $command): InterviewEvaluation
    {
        return $this->service->submit($command->companyId, $command->interviewId, $command->interviewerId, $command->data);
    }
}
