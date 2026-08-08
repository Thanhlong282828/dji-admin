<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\ApproveGovernmentFiling\ApproveGovernmentFilingCommand;
use Modules\Payroll\Application\Commands\CreateGovernmentFiling\CreateGovernmentFilingCommand;
use Modules\Payroll\Application\Commands\GenerateGovernmentDeclaration\GenerateGovernmentDeclarationCommand;
use Modules\Payroll\Application\Commands\RejectGovernmentFiling\RejectGovernmentFilingCommand;
use Modules\Payroll\Application\Commands\RetryGovernmentSubmission\RetryGovernmentSubmissionCommand;
use Modules\Payroll\Application\Commands\SubmitGovernmentFiling\SubmitGovernmentFilingCommand;
use Modules\Payroll\Application\Commands\ValidateGovernmentFiling\ValidateGovernmentFilingCommand;
use Modules\Payroll\Application\DTOs\GovernmentFilingDownloadData;
use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Application\DTOs\GovernmentFilingViewModel;
use Modules\Payroll\Application\Queries\DownloadGovernmentDocument\DownloadGovernmentDocumentQuery;
use Modules\Payroll\Application\Queries\GetGovernmentFilingCreateOptions\GetGovernmentFilingCreateOptionsQuery;
use Modules\Payroll\Application\Queries\GetGovernmentFilingDetail\GetGovernmentFilingDetailQuery;
use Modules\Payroll\Application\Queries\GetGovernmentFilingList\GetGovernmentFilingListQuery;
use Modules\Payroll\Application\Queries\GetSubmissionHistory\GetSubmissionHistoryQuery;
use Modules\Payroll\Presentation\Http\Requests\GovernmentFilingRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class GovernmentFilingController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'government.filing.view');
        $vm = $this->viewModel($this->queries->ask(new GetGovernmentFilingListQuery($companyId, $request->integer('tax_year'), (string) $request->query('status', ''))));

        return view('payroll::government-filing.index', ['c' => $companyId, 'vm' => $vm]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->company($request, 'government.filing.create');
        $vm = $this->viewModel($this->queries->ask(new GetGovernmentFilingCreateOptionsQuery($companyId)));

        return view('payroll::government-filing.create', ['c' => $companyId, 'vm' => $vm]);
    }

    public function store(GovernmentFilingRequest $request): RedirectResponse
    {
        $companyId = $this->company($request, 'government.filing.create');
        $result = $this->result($this->commands->dispatch(new CreateGovernmentFilingCommand($companyId, $request->integer('reconciliation_id'), $request->string('filing_type')->toString(), $this->user($request)->id, $request->string('idempotency_key')->toString())));

        return redirect()->route('admin.payroll.government-filings.show', ['filing' => $result->filingId, 'company_id' => $companyId])->with('success', 'Government filing snapshot created.');
    }

    public function show(Request $request, int $filing): View
    {
        $companyId = $this->company($request, 'government.filing.view');
        $vm = $this->viewModel($this->queries->ask(new GetGovernmentFilingDetailQuery($companyId, $filing)));
        $history = $this->viewModel($this->queries->ask(new GetSubmissionHistoryQuery($companyId, $filing)));

        return view('payroll::government-filing.show', ['c' => $companyId, 'vm' => $vm, 'history' => $history]);
    }

    public function mutate(GovernmentFilingRequest $request, int $filing, string $action): RedirectResponse
    {
        $permissions = ['generate' => 'government.filing.generate', 'validate' => 'government.filing.validate', 'approve' => 'government.filing.approve', 'submit' => 'government.filing.submit', 'retry' => 'government.filing.submit', 'reject' => 'government.filing.submit'];
        $companyId = $this->company($request, $permissions[$action] ?? abort(404));
        $actorId = $this->user($request)->id;
        $command = match ($action) {
            'generate' => new GenerateGovernmentDeclarationCommand($companyId, $filing, $actorId),
            'validate' => new ValidateGovernmentFilingCommand($companyId, $filing, $actorId),
            'approve' => new ApproveGovernmentFilingCommand($companyId, $filing, $actorId),
            'submit' => new SubmitGovernmentFilingCommand($companyId, $filing, $actorId, $request->string('idempotency_key')->toString()),
            'retry' => new RetryGovernmentSubmissionCommand($companyId, $filing, $actorId, $request->string('idempotency_key')->toString()),
            'reject' => new RejectGovernmentFilingCommand($companyId, $filing, $actorId, $request->string('reason')->toString()),
        };
        $this->result($this->commands->dispatch($command));

        return back()->with('success', 'Government filing action completed: '.$action.'.');
    }

    public function download(Request $request, int $filing, int $document): StreamedResponse
    {
        $companyId = $this->company($request, 'government.filing.download');
        $download = $this->queries->ask(new DownloadGovernmentDocumentQuery($companyId, $filing, $document, $this->user($request)->id));
        abort_unless($download instanceof GovernmentFilingDownloadData, 500);

        return response()->streamDownload(static function () use ($download): void {
            echo $download->bytes;
        }, $download->fileName, ['Content-Type' => $download->mimeType, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function company(Request $request, string $permission): int
    {
        $user = $this->user($request);
        $companyId = $request->integer('company_id');
        Gate::forUser($user)->authorize('workforce.company', [$permission, $companyId]);

        return $companyId;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function viewModel(mixed $value): GovernmentFilingViewModel
    {
        abort_unless($value instanceof GovernmentFilingViewModel, 500);

        return $value;
    }

    private function result(mixed $value): GovernmentFilingResult
    {
        abort_unless($value instanceof GovernmentFilingResult, 500);

        return $value;
    }
}
