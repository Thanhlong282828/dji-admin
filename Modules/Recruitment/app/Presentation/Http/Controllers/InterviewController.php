<?php

namespace Modules\Recruitment\Presentation\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Application\Commands\AssignInterviewers\AssignInterviewersCommand;
use Modules\Recruitment\Application\Commands\RemoveInterviewer\RemoveInterviewerCommand;
use Modules\Recruitment\Application\Commands\ReopenInterviewEvaluation\ReopenInterviewEvaluationCommand;
use Modules\Recruitment\Application\Commands\RescheduleApplicationInterview\RescheduleApplicationInterviewCommand;
use Modules\Recruitment\Application\Commands\SaveInterviewEvaluationDraft\SaveInterviewEvaluationDraftCommand;
use Modules\Recruitment\Application\Commands\ScheduleApplicationInterview\ScheduleApplicationInterviewCommand;
use Modules\Recruitment\Application\Commands\SubmitInterviewEvaluation\SubmitInterviewEvaluationCommand;
use Modules\Recruitment\Application\Commands\TransitionApplicationInterview\TransitionApplicationInterviewCommand;
use Modules\Recruitment\Application\DTOs\InterviewEvaluationData;
use Modules\Recruitment\Application\DTOs\InterviewScheduleData;
use Modules\Recruitment\Application\DTOs\InterviewScoreItem;
use Modules\Recruitment\Application\Queries\GetInterviewDetail\GetInterviewDetailQuery;
use Modules\Recruitment\Application\Queries\GetInterviewList\GetInterviewListQuery;
use Modules\Recruitment\Application\Queries\GetInterviewPanelSummary\GetInterviewPanelSummaryQuery;
use Modules\Recruitment\Domain\Enums\InterviewRecommendation;
use Modules\Recruitment\Domain\Enums\InterviewStatus;
use Modules\Recruitment\Domain\Enums\InterviewType;
use Modules\Recruitment\Domain\Models\ApplicationInterview;
use Modules\Recruitment\Presentation\Http\Requests\SaveInterviewEvaluationRequest;
use Modules\Recruitment\Presentation\Http\Requests\ScheduleInterviewRequest;

final readonly class InterviewController
{
    public function __construct(
        private CommandBusInterface $commands,
        private QueryBusInterface $queries,
        private TenantAccessService $tenant,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'interview.view');

        return view('recruitment::interviews.index', [
            'items' => $this->queries->ask(new GetInterviewListQuery($companyId)),
            'companyId' => $companyId,
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->company($request, 'interview.schedule');

        return view('recruitment::interviews.create', ['companyId' => $companyId]);
    }

    public function store(ScheduleInterviewRequest $request): RedirectResponse
    {
        $companyId = $this->company($request, 'interview.schedule');
        $ids = $request->input('interviewer_user_ids', []);
        $interviewerIds = array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($ids) ? $ids : []));
        $data = new InterviewScheduleData(
            $request->integer('application_id'),
            $request->string('title')->toString(),
            InterviewType::from($request->string('interview_type')->toString()),
            CarbonImmutable::parse($request->string('starts_at')->toString(), $request->string('timezone')->toString()),
            CarbonImmutable::parse($request->string('ends_at')->toString(), $request->string('timezone')->toString()),
            $request->string('timezone')->toString(),
            $this->nullableString($request, 'location'),
            $this->nullableString($request, 'meeting_url'),
            $this->nullableString($request, 'instructions'),
            $request->integer('round_number'),
            $interviewerIds,
            $request->filled('expected_application_version') ? $request->integer('expected_application_version') : null,
        );
        $interview = $this->commands->dispatch(new ScheduleApplicationInterviewCommand($companyId, $data, $this->actor($request)));
        abort_unless($interview instanceof ApplicationInterview, 500);

        return redirect()->route('admin.interviews.show', ['interview' => $interview->id, 'company_id' => $companyId])->with('success', 'Đã lên lịch interview.');
    }

    public function show(Request $request, int $interview): View
    {
        $companyId = $this->company($request, 'interview.view');

        return view('recruitment::interviews.show', [
            'interview' => $this->queries->ask(new GetInterviewDetailQuery($companyId, $interview)),
            'companyId' => $companyId,
        ]);
    }

    public function transition(Request $request, int $interview, InterviewStatus $target): RedirectResponse
    {
        $permission = match ($target) {
            InterviewStatus::Confirmed => 'interview.reschedule',
            InterviewStatus::Completed, InterviewStatus::NoShow => 'interview.complete',
            InterviewStatus::Cancelled => 'interview.cancel',
            default => 'interview.view',
        };
        $companyId = $this->company($request, $permission);
        $request->validate(['reason' => ['nullable', 'string', 'max:2000'], 'version' => ['nullable', 'integer', 'min:1']]);
        $this->commands->dispatch(new TransitionApplicationInterviewCommand(
            $companyId,
            $interview,
            $target,
            $this->actor($request),
            $this->nullableString($request, 'reason'),
            $request->filled('version') ? $request->integer('version') : null,
        ));

        return back()->with('success', 'Đã cập nhật interview.');
    }

    public function editEvaluation(Request $request, int $interview): View
    {
        $companyId = $this->company($request, 'interview.evaluate');

        return view('recruitment::interviews.evaluation', [
            'interview' => $this->queries->ask(new GetInterviewDetailQuery($companyId, $interview)),
            'companyId' => $companyId,
        ]);
    }

    public function panelSummary(Request $request, int $interview): View
    {
        $companyId = $this->company($request, 'interview.view-panel-summary');

        return view('recruitment::interviews.panel-summary', [
            'summary' => $this->queries->ask(new GetInterviewPanelSummaryQuery($companyId, $interview)),
            'companyId' => $companyId,
        ]);
    }

    public function saveEvaluationDraft(SaveInterviewEvaluationRequest $request, int $interview): RedirectResponse
    {
        $companyId = $this->company($request, 'interview.evaluate');
        $this->commands->dispatch(new SaveInterviewEvaluationDraftCommand($companyId, $interview, $this->actor($request), $this->evaluationData($request)));

        return redirect()->route('admin.interviews.show', ['interview' => $interview, 'company_id' => $companyId])->with('success', 'Đã lưu draft evaluation.');
    }

    public function submitEvaluation(SaveInterviewEvaluationRequest $request, int $interview): RedirectResponse
    {
        $companyId = $this->company($request, 'interview.evaluate');
        $this->commands->dispatch(new SubmitInterviewEvaluationCommand($companyId, $interview, $this->actor($request), $this->evaluationData($request)));

        return redirect()->route('admin.interviews.show', ['interview' => $interview, 'company_id' => $companyId])->with('success', 'Đã submit evaluation.');
    }

    public function reopenEvaluation(Request $request, int $evaluation): RedirectResponse
    {
        $companyId = $this->company($request, 'interview.reopen-evaluation');
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->commands->dispatch(new ReopenInterviewEvaluationCommand($companyId, $evaluation, $this->actor($request), $request->string('reason')->trim()->toString()));

        return back()->with('success', 'Đã reopen evaluation về draft.');
    }

    public function reschedule(Request $request, int $interview): RedirectResponse
    {
        $companyId = $this->company($request, 'interview.reschedule');
        $request->validate(['starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'timezone' => ['required', 'timezone'], 'version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:2000']]);
        $timezone = $request->string('timezone')->toString();
        $this->commands->dispatch(new RescheduleApplicationInterviewCommand($companyId, $interview, CarbonImmutable::parse($request->string('starts_at')->toString(), $timezone), CarbonImmutable::parse($request->string('ends_at')->toString(), $timezone), $timezone, $this->actor($request), $request->integer('version'), $request->string('reason')->trim()->toString()));

        return back()->with('success', 'Đã đổi lịch interview.');
    }

    public function assignInterviewers(Request $request, int $interview): RedirectResponse
    {
        $companyId = $this->company($request, 'interview.assign-interviewer');
        $request->validate(['interviewer_user_ids' => ['required', 'array', 'min:1'], 'interviewer_user_ids.*' => ['integer', 'distinct', 'exists:users,id']]);
        $raw = $request->input('interviewer_user_ids', []);
        $ids = array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($raw) ? $raw : []));
        $this->commands->dispatch(new AssignInterviewersCommand($companyId, $interview, $ids, $this->actor($request)));

        return back()->with('success', 'Đã bổ sung interviewer.');
    }

    public function removeInterviewer(Request $request, int $interview, int $user): RedirectResponse
    {
        $companyId = $this->company($request, 'interview.assign-interviewer');
        $this->commands->dispatch(new RemoveInterviewerCommand($companyId, $interview, $user, $this->actor($request)));

        return back()->with('success', 'Đã gỡ interviewer.');
    }

    private function evaluationData(SaveInterviewEvaluationRequest $request): InterviewEvaluationData
    {
        $rawItems = $request->input('items', []);
        $items = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $raw) {
                if (is_array($raw)) {
                    $items[] = new InterviewScoreItem(
                        is_string($raw['criterion_code'] ?? null) ? $raw['criterion_code'] : '',
                        is_string($raw['criterion_label'] ?? null) ? $raw['criterion_label'] : '',
                        is_numeric($raw['score'] ?? null) ? (int) $raw['score'] : -1,
                        is_numeric($raw['maximum_score'] ?? null) ? (int) $raw['maximum_score'] : 0,
                        is_numeric($raw['weight'] ?? null) ? (int) $raw['weight'] : 0,
                        is_string($raw['comment'] ?? null) ? $raw['comment'] : null,
                    );
                }
            }
        }

        return new InterviewEvaluationData(
            $items,
            InterviewRecommendation::from($request->string('recommendation')->toString()),
            $this->nullableString($request, 'strengths'),
            $this->nullableString($request, 'concerns'),
            $this->nullableString($request, 'private_notes'),
            $request->filled('version') ? $request->integer('version') : null,
        );
    }

    private function company(Request $request, string $permission): int
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($companyId > 0 && $user instanceof User && $user->can($permission) && $this->tenant->canAccess($user, $companyId), 403);

        return $companyId;
    }

    private function actor(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user->id;
    }

    private function nullableString(Request $request, string $key): ?string
    {
        $value = $request->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
