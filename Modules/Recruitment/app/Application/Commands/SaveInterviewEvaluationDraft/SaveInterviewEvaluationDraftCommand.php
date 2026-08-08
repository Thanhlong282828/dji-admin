<?php

namespace Modules\Recruitment\Application\Commands\SaveInterviewEvaluationDraft;

use Modules\Recruitment\Application\DTOs\InterviewEvaluationData;

final readonly class SaveInterviewEvaluationDraftCommand
{
    public function __construct(public int $companyId, public int $interviewId, public int $interviewerId, public InterviewEvaluationData $data) {}
}
