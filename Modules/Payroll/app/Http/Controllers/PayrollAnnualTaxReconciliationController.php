<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\ApproveAnnualTaxReconciliation\ApproveAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\CalculateAnnualTaxReconciliation\CalculateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\CollectAnnualTaxSources\CollectAnnualTaxSourcesCommand;
use Modules\Payroll\Application\Commands\CreateAnnualTaxPaymentIntent\CreateAnnualTaxPaymentIntentCommand;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\CreateAnnualTaxRefundIntent\CreateAnnualTaxRefundIntentCommand;
use Modules\Payroll\Application\Commands\CreateAnnualTaxSettlementJournal\CreateAnnualTaxSettlementJournalCommand;
use Modules\Payroll\Application\Commands\FinalizeAnnualTaxReconciliation\FinalizeAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\GenerateAnnualTaxStatement\GenerateAnnualTaxStatementCommand;
use Modules\Payroll\Application\Commands\RejectAnnualTaxReconciliation\RejectAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\RetryAnnualTaxDownstream\RetryAnnualTaxDownstreamCommand;
use Modules\Payroll\Application\Commands\ReviewAnnualTaxReconciliation\ReviewAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\SubmitAnnualTaxReconciliation\SubmitAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\SupersedeAnnualTaxReconciliation\SupersedeAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Commands\ValidateAnnualTaxReconciliation\ValidateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\DTOs\AnnualTaxDownloadData;
use Modules\Payroll\Application\DTOs\AnnualTaxDraftData;
use Modules\Payroll\Application\DTOs\AnnualTaxReconciliationDetailViewModel;
use Modules\Payroll\Application\DTOs\AnnualTaxReconciliationListViewModel;
use Modules\Payroll\Application\Queries\DownloadAnnualTaxStatement\DownloadAnnualTaxStatementQuery;
use Modules\Payroll\Application\Queries\GetAnnualTaxReconciliation\GetAnnualTaxReconciliationQuery;
use Modules\Payroll\Application\Queries\GetAnnualTaxReconciliationDetail\GetAnnualTaxReconciliationDetailQuery;
use Modules\Payroll\Application\Queries\GetAnnualTaxReconciliationList\GetAnnualTaxReconciliationListQuery;
use Modules\Payroll\Presentation\Http\Requests\AnnualTaxReconciliationRequest;
use Modules\Payroll\Presentation\Http\Requests\RejectAnnualTaxReconciliationRequest;
use Modules\Payroll\Presentation\Http\Requests\SupersedeAnnualTaxReconciliationRequest;

final readonly class PayrollAnnualTaxReconciliationController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'view');
        $result = $this->queries->ask(new GetAnnualTaxReconciliationListQuery($c, (string) $r->query('tax_year', ''), (string) $r->query('status', ''), (string) $r->query('result_type', ''), max(1, $r->integer('page', 1))));
        abort_unless($result instanceof AnnualTaxReconciliationListViewModel, 500);
        $data = $result->toViewData();

        return view('payroll::annual-tax.index', compact('c', 'data'));
    }

    public function create(Request $r): View
    {
        $c = $this->company($r, 'create');
        $data = $this->query(new GetAnnualTaxReconciliationQuery($c, 'create'));

        return view('payroll::annual-tax.create', compact('c', 'data'));
    }

    public function store(AnnualTaxReconciliationRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'create');
        $result = $this->commands->dispatch(new CreateAnnualTaxReconciliationCommand($c, $r->integer('employee_id'), $r->integer('tax_year'), $r->string('jurisdiction')->toString(), strtoupper($r->string('currency')->toString()), $r->string('idempotency_key')->toString(), $this->user($r)->id));
        abort_unless($result instanceof AnnualTaxDraftData, 500);

        return redirect()->route('admin.payroll.annual-tax.show', ['reconciliation' => $result->id, 'company_id' => $c])->with('success', 'Annual Tax draft created.');
    }

    public function show(Request $r, int $reconciliation): View
    {
        $c = $this->company($r, 'view');
        $result = $this->queries->ask(new GetAnnualTaxReconciliationDetailQuery($c, $reconciliation, max(1, $r->integer('sources_page', 1)), max(1, $r->integer('components_page', 1)), max(1, $r->integer('issues_page', 1)), max(1, $r->integer('history_page', 1))));
        abort_unless($result instanceof AnnualTaxReconciliationDetailViewModel, 500);
        $data = $result->toViewData();

        return view('payroll::annual-tax.show', compact('c', 'data'));
    }

    public function collect(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'calculate');
        $this->commands->dispatch(new CollectAnnualTaxSourcesCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('collect');
    }

    public function calculate(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'calculate');
        $this->commands->dispatch(new CalculateAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('calculate');
    }

    public function validateReconciliation(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'validate');
        $this->commands->dispatch(new ValidateAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('validate');
    }

    public function submit(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'submit');
        $this->commands->dispatch(new SubmitAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('submit');
    }

    public function review(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'review');
        $this->commands->dispatch(new ReviewAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('review');
    }

    public function approve(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'approve');
        $this->commands->dispatch(new ApproveAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('approve');
    }

    public function finalize(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'finalize');
        $this->commands->dispatch(new FinalizeAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('finalize');
    }

    public function reject(RejectAnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'reject');
        $this->commands->dispatch(new RejectAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id, $r->string('reason')->toString()));

        return $this->done('reject');
    }

    public function supersede(SupersedeAnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'supersede');
        $result = $this->commands->dispatch(new SupersedeAnnualTaxReconciliationCommand($c, $reconciliation, $this->user($r)->id, $r->string('reason_code')->toString(), $r->string('reason')->toString(), $r->string('idempotency_key')->toString()));
        $newId = is_object($result) && property_exists($result, 'newReconciliationId') ? $result->newReconciliationId : $reconciliation;

        return redirect()->route('admin.payroll.annual-tax.show', ['reconciliation' => $newId, 'company_id' => $c])->with('success', 'Annual Tax action completed: supersede');
    }

    public function generateStatement(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'statement.generate');
        $this->commands->dispatch(new GenerateAnnualTaxStatementCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('generate statement');
    }

    public function downloadStatement(Request $r, int $reconciliation, int $document): Response
    {
        $c = $this->company($r, 'statement.download');
        $result = $this->queries->ask(new DownloadAnnualTaxStatementQuery($c, $reconciliation, $document, $this->user($r)->id, $r->ip(), $r->userAgent()));
        abort_unless($result instanceof AnnualTaxDownloadData, 500);

        return response($result->bytes, 200, ['Content-Type' => $result->mime, 'Content-Length' => (string) $result->size, 'Content-Disposition' => 'attachment; filename="'.$result->fileName.'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function createPaymentIntent(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'payment.create');
        $this->commands->dispatch(new CreateAnnualTaxPaymentIntentCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('create payment eligibility');
    }

    public function createRefundIntent(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'refund.create');
        $this->commands->dispatch(new CreateAnnualTaxRefundIntentCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('create refund obligation');
    }

    public function createSettlementJournal(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'accounting.create');
        $this->commands->dispatch(new CreateAnnualTaxSettlementJournalCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('create settlement journal');
    }

    public function retryDownstream(AnnualTaxReconciliationRequest $r, int $reconciliation): RedirectResponse
    {
        $c = $this->company($r, 'downstream.retry');
        $this->commands->dispatch(new RetryAnnualTaxDownstreamCommand($c, $reconciliation, $this->user($r)->id));

        return $this->done('retry downstream');
    }

    private function done(string $action): RedirectResponse
    {
        return back()->with('success', 'Annual Tax action completed: '.$action);
    }

    /** @return array<string,mixed> */
    private function query(GetAnnualTaxReconciliationQuery $q): array
    {
        $data = $this->queries->ask($q);
        abort_unless(is_array($data), 500);

        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function company(Request $r, string $action): int
    {
        $c = $r->integer('company_id');
        Gate::forUser($this->user($r))->authorize('workforce.company', ['payroll.annual_tax.'.$action, $c]);

        return $c;
    }

    private function user(Request $r): User
    {
        $user = $r->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
