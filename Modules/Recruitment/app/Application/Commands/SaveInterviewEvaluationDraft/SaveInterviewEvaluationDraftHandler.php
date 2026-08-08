<?php

namespace Modules\Recruitment\Application\Commands\SaveInterviewEvaluationDraft;

use Modules\Recruitment\Domain\Models\InterviewEvaluation;
use Modules\Recruitment\Domain\Services\InterviewEvaluationService;

final readonly class SaveInterviewEvaluationDraftHandler
{
    public function __construct(private InterviewEvaluationService $service) {}

    public function handle(SaveInterviewEvaluationDraftCommand $command): InterviewEvaluation
    {
        return $this->service->saveDraft($command->companyId, $command->interviewId, $command->interviewerId, $command->data);
    }
}
