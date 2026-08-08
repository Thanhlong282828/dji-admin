<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Domain\Models\InterviewEvaluation;

final class InterviewEvaluationStateMachine
{
    public function assertEditable(InterviewEvaluation $evaluation): void
    {
        if ($evaluation->isSubmitted()) {
            throw ValidationException::withMessages(['evaluation' => 'Evaluation đã submit và đang bị khóa.']);
        }
    }

    public function assertReopenable(InterviewEvaluation $evaluation, string $reason): void
    {
        if (! $evaluation->isSubmitted() || trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Chỉ evaluation đã submit mới được reopen và phải có lý do.']);
        }
    }
}
