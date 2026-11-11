<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Application\DTOs\InterviewEvaluationData;
use Modules\Recruitment\Domain\Enums\InterviewStatus;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;
use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Models\InterviewEvaluation;

final readonly class InterviewEvaluationService
{
    public function __construct(private InterviewEvaluationStateMachine $states) {}

    public function saveDraft(int $companyId, int $interviewId, int $interviewerId, InterviewEvaluationData $data): InterviewEvaluation
    {
        return $this->save($companyId, $interviewId, $interviewerId, $data, false);
    }

    public function submit(int $companyId, int $interviewId, int $interviewerId, InterviewEvaluationData $data): InterviewEvaluation
    {
        return $this->save($companyId, $interviewId, $interviewerId, $data, true);
    }

    private function save(int $companyId, int $interviewId, int $interviewerId, InterviewEvaluationData $data, bool $submit): InterviewEvaluation
    {
        return DB::transaction(function () use ($companyId, $interviewId, $interviewerId, $data, $submit): InterviewEvaluation {
            $interview = ApplicationInterview::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($interviewId);
            $assigned = $interview->interviewers()->where('user_id', $interviewerId)->exists();
            if (! $assigned) {
                throw ValidationException::withMessages(['interviewer' => 'User không thuộc panel interview.']);
            }
            if ($submit && ! in_array($interview->status, [InterviewStatus::Confirmed, InterviewStatus::Completed], true)) {
                throw ValidationException::withMessages(['interview' => 'Chỉ interview đã confirm hoặc complete mới được submit evaluation.']);
            }
            $evaluation = InterviewEvaluation::query()->where('application_interview_id', $interviewId)
                ->where('interviewer_user_id', $interviewerId)->lockForUpdate()->first();
            if ($evaluation !== null) {
                $this->states->assertEditable($evaluation);
            }
            if ($evaluation !== null && $data->expectedVersion !== null && $evaluation->version !== $data->expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Evaluation đã thay đổi. Vui lòng tải lại.']);
            }
            $nextVersion = $evaluation === null ? 1 : $evaluation->version + 1;
            $payload = [
                'company_id' => $companyId,
                'application_interview_id' => $interviewId,
                'interviewer_user_id' => $interviewerId,
                'overall_score' => $data->overallScore(),
                'recommendation' => $data->recommendation->value,
                'scorecard' => $data->scorecard(),
                'strengths' => $data->strengths,
                'concerns' => $data->concerns,
                'notes' => $data->privateNotes,
                'submitted_at' => $submit ? now() : null,
            ];
            if ($evaluation === null) {
                $evaluation = InterviewEvaluation::query()->create($payload);
            } else {
                $evaluation->fill($payload);
            }
            // Concurrency metadata is system-owned and intentionally excluded from mass assignment.
            $evaluation->setAttribute('version', $nextVersion);
            $evaluation->save();
            activity('interview-evaluation')->performedOn($evaluation)->causedBy($interviewerId)
                ->event($submit ? 'submitted' : 'draft_saved')
                ->withProperties(['company_id' => $companyId, 'interview_id' => $interviewId, 'overall_score' => $data->overallScore()])
                ->log($submit ? 'Interview evaluation submitted' : 'Interview evaluation draft saved');
            event(new RecruitmentWorkflowChanged($companyId, 'interview_evaluation', $evaluation->id, $submit ? 'submitted' : 'draft_saved', $evaluation->version, $interviewerId));

            return $evaluation->refresh();
        }, 3);
    }

    public function reopen(int $companyId, int $evaluationId, int $actorId, string $reason): InterviewEvaluation
    {
        return DB::transaction(function () use ($companyId, $evaluationId, $actorId, $reason): InterviewEvaluation {
            $evaluation = InterviewEvaluation::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($evaluationId);
            $this->states->assertReopenable($evaluation, $reason);
            $evaluation->setAttribute('submitted_at', null);
            $evaluation->setAttribute('reopened_by', $actorId);
            $evaluation->setAttribute('reopened_at', now());
            $evaluation->setAttribute('reopen_reason', $reason);
            $evaluation->setAttribute('version', $evaluation->version + 1);
            $evaluation->save();
            event(new RecruitmentWorkflowChanged($companyId, 'interview_evaluation', $evaluation->id, 'reopened', $evaluation->version, $actorId));

            return $evaluation->refresh();
        }, 3);
    }
}
