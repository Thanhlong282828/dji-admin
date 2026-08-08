<?php

namespace Modules\Audit\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Audit\Application\Commands\ApplyRetentionPolicyCommand;
use Modules\Audit\Application\Commands\ArchiveAuditRangeCommand;
use Modules\Audit\Application\Commands\CreateAuditExportCommand;
use Modules\Audit\Application\Queries\AuditPageQuery;
use Modules\Audit\Domain\Services\AuditExportService;
use Modules\Audit\Domain\Services\AuditTenantAccessService;
use Modules\Audit\Presentation\Http\Requests\AuditRequest;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;

final class AuditController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries, private readonly CommandBusInterface $commands, private readonly AuditTenantAccessService $tenant, private readonly AuditExportService $exportService) {}

    private function page(AuditRequest $request, string $page, ?int $id = null): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->companyId();
        $this->tenant->authorize($user, $company);

        return view('audit::hub', ['vm' => $this->queries->ask(new AuditPageQuery($company, $page, $id, $request->search())), 'page' => $page, 'companyId' => $company]);
    }

    public function dashboard(AuditRequest $r): View
    {
        return $this->page($r, 'dashboard');
    }

    public function timeline(AuditRequest $r): View
    {
        return $this->page($r, 'timeline');
    }

    public function event(AuditRequest $r, int $event): View
    {
        return $this->page($r, 'event', $event);
    }

    public function actors(AuditRequest $r): View
    {
        return $this->page($r, 'actor');
    }

    public function resources(AuditRequest $r): View
    {
        return $this->page($r, 'resource');
    }

    public function exports(AuditRequest $r): View
    {
        return $this->page($r, 'exports');
    }

    public function retention(AuditRequest $r): View
    {
        return $this->page($r, 'retention');
    }

    public function createExport(AuditRequest $request): RedirectResponse
    {
        $user = $this->actor($request);
        $company = $request->companyId();
        $this->tenant->authorize($user, $company);
        $this->commands->dispatch(new CreateAuditExportCommand($company, (int) $user->id, $request->exportFormat(), ['search' => $request->search()], (string) Str::uuid()));

        return redirect()->route('admin.audit.exports', ['company_id' => $company])->with('success', 'Audit export generated securely.');
    }

    public function download(AuditRequest $request, int $export): Response
    {
        $user = $this->actor($request);
        $company = $request->companyId();
        $this->tenant->authorize($user, $company);
        $artifact = $this->exportService->verifiedArtifact($company, $export, (int) $user->id);

        return response($artifact['contents'], 200, ['Content-Type' => $artifact['mime'], 'Content-Disposition' => 'attachment; filename="'.$artifact['name'].'"']);
    }

    public function applyRetention(AuditRequest $request): RedirectResponse
    {
        $user = $this->actor($request);
        $company = $request->companyId();
        $this->tenant->authorize($user, $company);
        $this->commands->dispatch(new ApplyRetentionPolicyCommand($company, (int) $user->id, $request->policyName(), $request->retentionDays()));

        return redirect()->route('admin.audit.retention', ['company_id' => $company])->with('success', 'Retention policy recorded without deleting audit evidence.');
    }

    public function archive(AuditRequest $request): RedirectResponse
    {
        $user = $this->actor($request);
        $company = $request->companyId();
        $this->tenant->authorize($user, $company);
        $key = hash('sha256', implode('|', [$company, $request->rangeStart(), $request->rangeEnd()]));
        $this->commands->dispatch(new ArchiveAuditRangeCommand($company, (int) $user->id, $request->rangeStart(), $request->rangeEnd(), $key));

        return redirect()->route('admin.audit.retention', ['company_id' => $company])->with('success', 'Immutable audit archive created without deleting source evidence.');
    }

    private function actor(AuditRequest $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
