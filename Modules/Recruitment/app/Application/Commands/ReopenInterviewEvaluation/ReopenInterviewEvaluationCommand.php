<?php

namespace Modules\Recruitment\Application\Commands\ReopenInterviewEvaluation;

final readonly class ReopenInterviewEvaluationCommand
{
    public function __construct(public int $companyId, public int $evaluationId, public int $actorId, public string $reason) {}
}
