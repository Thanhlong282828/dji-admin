<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\Commands\ApproveDataCorrection\ApproveDataCorrectionCommand;
use Modules\Employee\Application\Commands\ArchiveEmployeeDocument\ArchiveEmployeeDocumentCommand;
use Modules\Employee\Application\Commands\RecordHRGovernanceAccess\RecordHRGovernanceAccessCommand;
use Modules\Employee\Application\Commands\ResolveDataQualityIssue\ResolveDataQualityIssueCommand;
use Modules\Employee\Application\Commands\RunDataQualityCheck\RunDataQualityCheckCommand;
use Modules\Employee\Application\Queries\ConsentAuditView\ConsentAuditViewQuery;
use Modules\Employee\Application\Queries\DocumentGovernanceView\DocumentGovernanceViewQuery;
use Modules\Employee\Application\Queries\HRDataQualityDashboard\HRDataQualityDashboardQuery;
use Modules\Employee\Policies\HRGovernancePolicy;
use Modules\Employee\Presentation\Http\Requests\HRGovernanceActionRequest;

final readonly class HRGovernanceController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private HRGovernancePolicy $policy) {}

    public function dashboard(Request $request): View
    {
        return $this->page($request, 'dashboard');
    }

    public function issues(Request $request): View
    {
        return $this->page($request, 'issues');
    }

    public function audit(Request $request): View
    {
        $this->authorize($request, $this->company($request), 'hr.audit.view');

        return $this->page($request, 'audit', false);
    }

    public function consents(Request $request): View
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.governance.view');
        $viewModel = $this->queries->ask(new ConsentAuditViewQuery($company));
        $this->recordView($request, $company, 'consent_governance');

        return view('employee::governance.consents', ['viewModel' => $viewModel, 'companyId' => $company]);
    }

    public function documents(Request $request): View
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.governance.view');
        $viewModel = $this->queries->ask(new DocumentGovernanceViewQuery($company));
        $this->recordView($request, $company, 'document_governance');

        return view('employee::governance.documents', compact('viewModel') + ['companyId' => $company]);
    }

    public function run(HRGovernanceActionRequest $request): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.quality.run');
        $this->commands->dispatch(new RunDataQualityCheckCommand($company, $this->actor($request), now()->toDateString(), $request->idempotencyKey()));

        return back()->with('success', 'Đã hoàn thành kiểm tra chất lượng dữ liệu.');
    }

    public function transition(HRGovernanceActionRequest $request, int $issue): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.governance.manage');
        $this->commands->dispatch(new ResolveDataQualityIssueCommand($company, $issue, $this->actor($request), $request->action(), $request->reason('Đã xác minh evidence correction.'), $request->idempotencyKey()));

        return back()->with('success', 'Đã cập nhật trạng thái issue.');
    }

    public function approve(HRGovernanceActionRequest $request, int $issue): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.governance.manage');
        $this->commands->dispatch(new ApproveDataCorrectionCommand($company, $issue, $this->actor($request), $request->reason('Correction được phê duyệt.'), $request->idempotencyKey()));

        return back()->with('success', 'Đã phê duyệt correction.');
    }

    public function archiveDocument(HRGovernanceActionRequest $request, int $document): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.document.manage');
        $retention = $request->validated('retention_date');
        $this->commands->dispatch(new ArchiveEmployeeDocumentCommand($company, $document, $this->actor($request), $request->idempotencyKey(), is_string($retention) ? $retention : null));

        return back()->with('success', 'Tài liệu đã được lưu trữ theo governance lifecycle.');
    }

    private function page(Request $request, string $page, bool $authorize = true): View
    {
        $company = $this->company($request);
        if ($authorize) {
            $this->authorize($request, $company, 'hr.governance.view');
        }

        $viewModel = $this->queries->ask(new HRDataQualityDashboardQuery($company));
        $this->recordView($request, $company, $page === 'audit' ? 'governance_audit' : 'quality_governance');

        $view = match ($page) {
            'dashboard' => 'employee::governance.dashboard',
            'issues' => 'employee::governance.issues',
            'audit' => 'employee::governance.audit',
            default => abort(404),
        };

        return view($view, ['viewModel' => $viewModel, 'companyId' => $company]);
    }

    private function company(Request $request): int
    {
        $id = $request->integer('company_id');
        abort_if($id < 1, 422);

        return $id;
    }

    private function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }

    private function authorize(Request $request, int $company, string $permission): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->policy->canAccessCompany($user, $company), 404);
        abort_unless($this->policy->allows($user, $company, $permission), 403);
    }

    private function recordView(Request $request, int $company, string $entityType): void
    {
        $this->commands->dispatch(new RecordHRGovernanceAccessCommand($company, $this->actor($request), $entityType, $company, 'data_viewed', hash('sha256', $entityType.'|'.$company.'|'.$this->actor($request).'|'.now()->format('Y-m-d-H-i-s-u'))));
    }
}
