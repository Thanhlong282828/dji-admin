<?php

namespace Modules\Recruitment\Application\Commands\ReopenInterviewEvaluation;

use Modules\Recruitment\Domain\Models\InterviewEvaluation;
use Modules\Recruitment\Domain\Services\InterviewEvaluationService;

final readonly class ReopenInterviewEvaluationHandler
{
    public function __construct(private InterviewEvaluationService $service) {}

    public function handle(ReopenInterviewEvaluationCommand $command): InterviewEvaluation
    {
        return $this->service->reopen($command->companyId, $command->evaluationId, $command->actorId, $command->reason);
    }
}
