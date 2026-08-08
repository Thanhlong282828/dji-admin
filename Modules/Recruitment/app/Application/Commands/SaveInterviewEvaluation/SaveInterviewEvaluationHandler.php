<?php

namespace Modules\Recruitment\Application\Commands\SaveInterviewEvaluation;

use Modules\Recruitment\Domain\Models\InterviewEvaluation;
use Modules\Recruitment\Domain\Services\InterviewEvaluationService;

final readonly class SaveInterviewEvaluationHandler
{
    public function __construct(private InterviewEvaluationService $service) {}

    public function handle(SaveInterviewEvaluationCommand $command): InterviewEvaluation
    {
        return $command->submit
            ? $this->service->submit($command->companyId, $command->interviewId, $command->interviewerId, $command->data)
            : $this->service->saveDraft($command->companyId, $command->interviewId, $command->interviewerId, $command->data);
    }
}
