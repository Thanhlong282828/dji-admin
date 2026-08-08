<?php

namespace Modules\Recruitment\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Application\DTOs\InterviewScheduleData;
use Modules\Recruitment\Domain\Enums\ApplicationStage;
use Modules\Recruitment\Domain\Enums\InterviewStatus;
use Modules\Recruitment\Domain\Enums\InterviewType;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Domain\Models\ApplicationInterviewer;

final readonly class ApplicationInterviewService
{
    public function __construct(private ApplicationInterviewStateMachine $states) {}

    public function schedule(int $companyId, InterviewScheduleData $data, int $actorId): ApplicationInterview
    {
        return DB::transaction(function () use ($companyId, $data, $actorId): ApplicationInterview {
            $application = $this->application($companyId, $data->applicationId);
            if ($data->expectedApplicationVersion !== null && $application->version !== $data->expectedApplicationVersion) {
                throw ValidationException::withMessages(['version' => 'Application đã thay đổi. Vui lòng tải lại.']);
            }
            if (in_array($application->stage, [ApplicationStage::Hired, ApplicationStage::Rejected], true)) {
                throw ValidationException::withMessages(['application' => 'Không thể lên lịch cho Application terminal.']);
            }
            $this->validateSchedule($data->type, $data->startsAt, $data->endsAt, $data->location, $data->meetingUrl);
            $interviewers = array_values(array_unique($data->interviewerUserIds));
            if ($interviewers === []) {
                throw ValidationException::withMessages(['interviewer_user_ids' => 'Phải chọn ít nhất một interviewer.']);
            }
            $authorizedCount = DB::table('company_user_access')->where('company_id', $companyId)->whereIn('user_id', $interviewers)->distinct()->count('user_id');
            if ($authorizedCount !== count($interviewers)) {
                throw ValidationException::withMessages(['interviewer_user_ids' => 'Interviewer không thuộc Company hiện tại.']);
            }
            $duplicateRound = ApplicationInterview::query()
                ->where('application_id', $application->id)
                ->where('round_number', $data->roundNumber)
                ->whereNotIn('status', ['cancelled'])
                ->exists();
            if ($duplicateRound) {
                throw ValidationException::withMessages(['round_number' => 'Round interview này đã tồn tại.']);
            }

            $interview = ApplicationInterview::query()->create([
                'company_id' => $companyId,
                'application_id' => $application->id,
                'round_number' => $data->roundNumber,
                'title' => $data->title,
                'interview_type' => $data->type->value,
                'status' => InterviewStatus::Scheduled->value,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'timezone' => $data->timezone,
                'location' => $data->location,
                'meeting_url' => $data->meetingUrl,
                'instructions' => $data->instructions,
                'scheduled_by' => $actorId,
            ]);
            foreach ($interviewers as $userId) {
                ApplicationInterviewer::query()->create([
                    'company_id' => $companyId,
                    'application_interview_id' => $interview->id,
                    'user_id' => $userId,
                    'role' => 'panelist',
                ]);
            }
            activity('interview')->performedOn($interview)->causedBy($actorId)->event('scheduled')
                ->withProperties(['company_id' => $companyId, 'application_id' => $application->id, 'round' => $data->roundNumber])
                ->log('Application interview scheduled');
            $interview->refresh();
            event(new RecruitmentWorkflowChanged($companyId, 'interview', $interview->id, 'scheduled', $interview->version, $actorId));

            return $interview;
        }, 3);
    }

    /** @return LengthAwarePaginator<int, ApplicationInterview> */
    public function list(int $companyId): LengthAwarePaginator
    {
        return ApplicationInterview::query()
            ->with(['application.candidate:id,full_name', 'interviewers.user:id,name'])
            ->where('company_id', $companyId)
            ->orderBy('starts_at')
            ->paginate(20);
    }

    public function detail(int $companyId, int $interviewId): ApplicationInterview
    {
        return ApplicationInterview::query()
            ->with(['application.candidate:id,full_name', 'application.requisition.jobPosition:id,title', 'interviewers.user:id,name', 'evaluations'])
            ->where('company_id', $companyId)
            ->findOrFail($interviewId);
    }

    public function transition(int $companyId, int $interviewId, InterviewStatus $target, int $actorId, ?string $reason, ?int $expectedVersion): ApplicationInterview
    {
        return DB::transaction(function () use ($companyId, $interviewId, $target, $actorId, $reason, $expectedVersion): ApplicationInterview {
            $interview = ApplicationInterview::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($interviewId);
            if ($expectedVersion !== null && $interview->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Interview đã thay đổi. Vui lòng tải lại.']);
            }
            $from = $interview->status;
            $this->states->assert($from, $target);
            if ($target === InterviewStatus::Cancelled && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Hủy interview bắt buộc có lý do.']);
            }
            $changes = ['status' => $target->value, 'version' => $interview->version + 1];
            if ($target === InterviewStatus::Cancelled) {
                $changes += ['cancelled_by' => $actorId, 'cancelled_at' => now(), 'cancellation_reason' => $reason];
            }
            if ($target === InterviewStatus::Completed) {
                $changes['completed_at'] = now();
            }
            $interview->update($changes);
            activity('interview')->performedOn($interview)->causedBy($actorId)->event($target->value)
                ->withProperties(['company_id' => $companyId, 'from' => $from->value, 'to' => $target->value, 'reason' => $reason])
                ->log('Application interview status changed');
            event(new RecruitmentWorkflowChanged($companyId, 'interview', $interview->id, $target->value, $interview->version, $actorId));

            return $interview->refresh();
        }, 3);
    }

    public function reschedule(int $companyId, int $interviewId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timezone, int $actorId, int $expectedVersion, string $reason): ApplicationInterview
    {
        return DB::transaction(function () use ($companyId, $interviewId, $startsAt, $endsAt, $timezone, $actorId, $expectedVersion, $reason): ApplicationInterview {
            $interview = ApplicationInterview::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($interviewId);
            if ($interview->version !== $expectedVersion || ! in_array($interview->status, [InterviewStatus::Scheduled, InterviewStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['version' => 'Interview stale hoặc không còn được phép reschedule.']);
            }
            $this->validateSchedule($interview->interview_type, $startsAt, $endsAt, $interview->location, $interview->meeting_url);
            $before = ['starts_at' => $interview->starts_at->toIso8601String(), 'ends_at' => $interview->ends_at->toIso8601String(), 'timezone' => $interview->timezone];
            $after = ['starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String(), 'timezone' => $timezone];
            $interview->update(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'timezone' => $timezone, 'version' => $interview->version + 1]);
            DB::table('application_interview_histories')->insert(['company_id' => $companyId, 'application_interview_id' => $interviewId, 'event' => 'rescheduled', 'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR), 'reason' => $reason, 'actor_id' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            event(new RecruitmentWorkflowChanged($companyId, 'interview', $interviewId, 'rescheduled', $interview->version, $actorId));

            return $interview->refresh();
        }, 3);
    }

    /** @param list<int> $userIds */
    public function assignInterviewers(int $companyId, int $interviewId, array $userIds, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $interviewId, $userIds, $actorId): void {
            $interview = ApplicationInterview::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($interviewId);
            if (in_array($interview->status, [InterviewStatus::Completed, InterviewStatus::Cancelled, InterviewStatus::NoShow], true)) {
                throw ValidationException::withMessages(['interview' => 'Không thể thêm interviewer vào interview terminal.']);
            }
            $ids = array_values(array_unique($userIds));
            $count = DB::table('company_user_access')->where('company_id', $companyId)->whereIn('user_id', $ids)->distinct()->count('user_id');
            if ($ids === [] || $count !== count($ids)) {
                throw ValidationException::withMessages(['interviewer_user_ids' => 'Interviewer không thuộc Company hiện tại.']);
            }
            foreach ($ids as $userId) {
                ApplicationInterviewer::query()->firstOrCreate(['application_interview_id' => $interviewId, 'user_id' => $userId], ['company_id' => $companyId, 'role' => 'panelist']);
            }
            activity('interview')->performedOn($interview)->causedBy($actorId)->event('interviewers_assigned')->withProperties(['company_id' => $companyId, 'user_ids' => $ids])->log('Interviewers assigned');
            event(new RecruitmentWorkflowChanged($companyId, 'interview', $interviewId, 'interviewers_assigned', $interview->version, $actorId));
        }, 3);
    }

    public function removeInterviewer(int $companyId, int $interviewId, int $userId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $interviewId, $userId, $actorId): void {
            $interview = ApplicationInterview::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($interviewId);
            $hasEvaluation = $interview->evaluations()->where('interviewer_user_id', $userId)->exists();
            if ($hasEvaluation) {
                throw ValidationException::withMessages(['interviewer' => 'Không thể xóa interviewer đã có evaluation.']);
            }
            $interview->interviewers()->where('user_id', $userId)->delete();
            activity('interview')->performedOn($interview)->causedBy($actorId)->event('interviewer_removed')->withProperties(['company_id' => $companyId, 'user_id' => $userId])->log('Interviewer removed');
            event(new RecruitmentWorkflowChanged($companyId, 'interview', $interviewId, 'interviewer_removed', $interview->version, $actorId));
        }, 3);
    }

    private function application(int $companyId, int $applicationId): Application
    {
        return Application::query()
            ->whereHas('requisition.jobPosition', static fn ($query) => $query->where('company_id', $companyId))
            ->lockForUpdate()
            ->findOrFail($applicationId);
    }

    private function validateSchedule(InterviewType $type, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $location, ?string $meetingUrl): void
    {
        if ($startsAt->isPast() || $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['starts_at' => 'Thời gian interview không hợp lệ.']);
        }
        if ($type === InterviewType::Online && ($meetingUrl === null || filter_var($meetingUrl, FILTER_VALIDATE_URL) === false)) {
            throw ValidationException::withMessages(['meeting_url' => 'Interview online cần meeting URL hợp lệ.']);
        }
        if ($type === InterviewType::Offline && ($location === null || trim($location) === '')) {
            throw ValidationException::withMessages(['location' => 'Interview offline cần địa điểm.']);
        }
    }
}
