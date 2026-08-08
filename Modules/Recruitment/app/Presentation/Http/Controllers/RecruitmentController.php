<?php

namespace Modules\Recruitment\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Recruitment\Application\Commands\ArchiveCandidate\ArchiveCandidateCommand;
use Modules\Recruitment\Application\Commands\CreateApplication\CreateApplicationCommand;
use Modules\Recruitment\Application\Commands\CreateCandidate\CreateCandidateCommand;
use Modules\Recruitment\Application\Commands\CreateRecruitmentRequisition\CreateRecruitmentRequisitionCommand;
use Modules\Recruitment\Application\Commands\MergeCandidates\MergeCandidatesCommand;
use Modules\Recruitment\Application\Commands\MoveApplicationStage\MoveApplicationStageCommand;
use Modules\Recruitment\Application\Commands\RemoveCandidateResume\RemoveCandidateResumeCommand;
use Modules\Recruitment\Application\Commands\RestoreCandidate\RestoreCandidateCommand;
use Modules\Recruitment\Application\Commands\TransitionRecruitmentRequisition\TransitionRecruitmentRequisitionCommand;
use Modules\Recruitment\Application\Commands\UploadCandidateResume\UploadCandidateResumeCommand;
use Modules\Recruitment\Application\Queries\GetApplicationPipeline\GetApplicationPipelineQuery;
use Modules\Recruitment\Application\Queries\GetCandidateList\GetCandidateListQuery;
use Modules\Recruitment\Application\Queries\GetCandidateResumeDownload\GetCandidateResumeDownloadQuery;
use Modules\Recruitment\Application\Queries\GetRecruitmentDashboard\GetRecruitmentDashboardQuery;
use Modules\Recruitment\Application\Queries\GetRecruitmentRequisitionList\GetRecruitmentRequisitionListQuery;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Presentation\Http\Requests\StoreApplicationRequest;
use Modules\Recruitment\Presentation\Http\Requests\StoreCandidateRequest;
use Modules\Recruitment\Presentation\Http\Requests\StoreRecruitmentRequisitionRequest;
use Modules\Recruitment\Presentation\Http\Requests\UploadCandidateResumeRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RecruitmentController extends Controller
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private TenantAccessService $tenant) {}

    public function dashboard(Request $r): View
    {
        $c = $this->company($r, 'recruitment-requisition.view');

        return view('recruitment::dashboard.index', ['stats' => $this->queries->ask(new GetRecruitmentDashboardQuery($c, $this->stringKeyed($r->query()))), 'companyId' => $c]);
    }

    public function requisitions(Request $r): View
    {
        $c = $this->company($r, 'recruitment-requisition.view');

        return view('recruitment::requisitions.index', ['items' => $this->queries->ask(new GetRecruitmentRequisitionListQuery($c, $this->stringKeyed($r->query()))), 'companyId' => $c]);
    }

    public function storeRequisition(StoreRecruitmentRequisitionRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'recruitment-requisition.create');
        $this->commands->dispatch(new CreateRecruitmentRequisitionCommand($c, $this->stringKeyed($r->safe()->except('company_id')), $this->actor($r)));

        return back()->with('success', 'Đã tạo yêu cầu tuyển dụng nháp.');
    }

    public function transition(Request $r, int $requisition, string $status): RedirectResponse
    {
        $c = $this->company($r, 'recruitment-requisition.'.$this->permissionAction($status));
        $reason = $r->string('reason')->trim()->toString();
        $this->commands->dispatch(new TransitionRecruitmentRequisitionCommand($c, $requisition, $status, $this->actor($r), $reason === '' ? null : $reason));

        return back()->with('success', 'Đã cập nhật trạng thái yêu cầu.');
    }

    public function candidates(Request $r): View
    {
        $c = $this->company($r, 'candidate.view');

        return view('recruitment::candidates.index', ['items' => $this->queries->ask(new GetCandidateListQuery($c, $this->stringKeyed($r->query()))), 'companyId' => $c]);
    }

    public function storeCandidate(StoreCandidateRequest $r): RedirectResponse
    {
        $companyId = $this->company($r, 'candidate.create');
        $this->commands->dispatch(new CreateCandidateCommand(
            $companyId,
            $this->stringKeyed($r->safe()->except('company_id')),
            $this->actor($r),
        ));

        return back()->with('success', 'Đã thêm ứng viên.');
    }

    public function archiveCandidate(Request $request, int $candidate): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate.archive');
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->commands->dispatch(new ArchiveCandidateCommand(
            $companyId,
            $candidate,
            $this->actor($request),
            $request->string('reason')->trim()->toString(),
        ));

        return back()->with('success', 'Đã lưu trữ ứng viên.');
    }

    public function restoreCandidate(Request $request, int $candidate): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate.restore');
        $this->commands->dispatch(new RestoreCandidateCommand($companyId, $candidate, $this->actor($request)));

        return back()->with('success', 'Đã khôi phục ứng viên.');
    }

    public function mergeCandidates(Request $request, int $candidate): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate.merge');
        $request->validate(['secondary_candidate_id' => ['required', 'integer', 'min:1']]);
        $this->commands->dispatch(new MergeCandidatesCommand(
            $companyId,
            $candidate,
            $request->integer('secondary_candidate_id'),
            $this->actor($request),
        ));

        return redirect()->route('admin.candidates.index', ['company_id' => $companyId])->with('success', 'Đã hợp nhất ứng viên.');
    }

    public function uploadCandidateResume(UploadCandidateResumeRequest $request, int $candidate): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate.upload-resume');
        $file = $request->file('resume');
        abort_unless($file instanceof UploadedFile, 422);
        $this->commands->dispatch(new UploadCandidateResumeCommand($companyId, $candidate, $file, $this->actor($request)));

        return back()->with('success', 'Đã lưu resume vào private storage.');
    }

    public function downloadCandidateResume(Request $request, int $candidate): BinaryFileResponse
    {
        $companyId = $this->company($request, 'candidate.download-resume');
        $record = $this->queries->ask(new GetCandidateResumeDownloadQuery($companyId, $candidate));
        abort_unless($record instanceof Candidate, 404);
        $storageKey = $record->resume_storage_key;
        abort_unless(is_string($storageKey), 404);

        return response()->download(
            Storage::disk('local')->path($storageKey),
            'candidate-'.$record->uuid.'-resume',
            ['Content-Type' => $record->resume_mime_type ?? 'application/octet-stream'],
        );
    }

    public function removeCandidateResume(Request $request, int $candidate): RedirectResponse
    {
        $companyId = $this->company($request, 'candidate.upload-resume');
        $this->commands->dispatch(new RemoveCandidateResumeCommand($companyId, $candidate, $this->actor($request)));

        return back()->with('success', 'Đã gỡ resume.');
    }

    public function pipeline(Request $r): View
    {
        $c = $this->company($r, 'application.view');

        return view('recruitment::applications.pipeline', ['pipeline' => $this->queries->ask(new GetApplicationPipelineQuery($c, $r->integer('requisition_id') ?: null)), 'companyId' => $c]);
    }

    public function storeApplication(StoreApplicationRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'application.create');
        $this->commands->dispatch(new CreateApplicationCommand($c, $this->stringKeyed($r->safe()->except('company_id')), $this->actor($r)));

        return back()->with('success', 'Đã tiếp nhận application.');
    }

    public function move(Request $r, int $application): RedirectResponse
    {
        $c = $this->company($r, 'application.change-stage');
        $r->validate([
            'stage' => ['required', 'string'],
            'version' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $stage = $r->string('stage')->toString();
        $version = $r->filled('version') ? $r->integer('version') : null;
        $reason = $r->string('reason')->trim()->toString();
        $this->commands->dispatch(new MoveApplicationStageCommand(
            $c,
            $application,
            $stage,
            $this->actor($r),
            $version,
            $reason === '' ? null : $reason,
        ));

        return back()->with('success', 'Đã chuyển stage.');
    }

    private function company(Request $r, string $permission): int
    {
        $c = $r->integer('company_id');
        $u = $r->user();
        abort_unless($c > 0 && $u && $u->can($permission) && $this->tenant->canAccess($u, $c), 403);

        return $c;
    }

    private function actor(Request $r): int
    {
        $user = $r->user();
        abort_unless($user instanceof User, 401);

        return $user->id;
    }

    private function permissionAction(string $status): string
    {
        return match ($status) {
            'approval' => 'submit',
            'approved' => 'approve',
            'rejected' => 'reject',
            'draft' => 'update',
            'open' => 'open',
            'paused' => 'pause',
            'filled' => 'fill',
            'cancelled' => 'cancel',
            default => 'update'
        };
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<string, mixed>
     */
    private function stringKeyed(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
