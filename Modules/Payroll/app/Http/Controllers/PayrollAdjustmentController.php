<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\CreateAdjustmentAccountingJournal\CreateAdjustmentAccountingJournalCommand;
use Modules\Payroll\Application\Commands\CreateAdjustmentPaymentInstruction\CreateAdjustmentPaymentInstructionCommand;
use Modules\Payroll\Application\Commands\CreatePayrollAdjustment\CreatePayrollAdjustmentCommand;
use Modules\Payroll\Application\Commands\GenerateAdjustmentStatement\GenerateAdjustmentStatementCommand;
use Modules\Payroll\Application\Commands\ProcessPayrollAdjustment\ProcessPayrollAdjustmentCommand;
use Modules\Payroll\Application\Commands\RetryAdjustmentDownstream\RetryAdjustmentDownstreamCommand;
use Modules\Payroll\Application\DTOs\PayrollAdjustmentViewModel;
use Modules\Payroll\Application\Queries\DownloadAdjustmentStatement\DownloadAdjustmentStatementQuery;
use Modules\Payroll\Application\Queries\GetPayrollAdjustment\GetPayrollAdjustmentQuery;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamType;
use Modules\Payroll\Presentation\Http\Requests\PayrollAdjustmentRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PayrollAdjustmentController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'payroll.adjustment.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->ask(new GetPayrollAdjustmentQuery($c, 'list', filters: $f));

        return view('payroll::adjustments.index', compact('c', 'f', 'vm'));
    }

    public function create(Request $r): View
    {
        $c = $this->company($r, 'payroll.adjustment.create');
        $vm = $this->ask(new GetPayrollAdjustmentQuery($c, 'create'));

        return view('payroll::adjustments.create', compact('c', 'vm'));
    }

    public function store(PayrollAdjustmentRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'payroll.adjustment.create');
        $id = $this->commands->dispatch(new CreatePayrollAdjustmentCommand($c, $r->integer('source_payroll_item_id'), $r->integer('adjustment_reason_id'), $r->string('adjustment_type')->toString(), $r->string('requested_effective_date')->toString(), $r->filled('target_payment_period_id') ? $r->integer('target_payment_period_id') : null, $r->string('description')->toString(), $r->filled('evidence_document_id') ? $r->integer('evidence_document_id') : null, $r->string('idempotency_key')->toString(), $this->user($r)->id, $r->filled('supersedes_adjustment_request_id') ? $r->integer('supersedes_adjustment_request_id') : null));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.adjustments.show', ['adjustment' => $id, 'company_id' => $c])->with('success', 'Adjustment request created without modifying historical payroll.');
    }

    public function show(Request $r, int $adjustment): View
    {
        $c = $this->company($r, 'payroll.adjustment.view');
        $vm = $this->ask(new GetPayrollAdjustmentQuery($c, 'detail', $adjustment));

        return view('payroll::adjustments.show', compact('c', 'vm'));
    }

    public function mutate(PayrollAdjustmentRequest $r, int $adjustment, string $action): RedirectResponse
    {
        $p = ['calculate' => 'calculate', 'validate' => 'calculate', 'submit' => 'update', 'review' => 'review', 'approve' => 'approve', 'reject' => 'approve', 'apply' => 'apply'][$action] ?? abort(404);
        $c = $this->company($r, 'payroll.adjustment.'.$p);
        $this->commands->dispatch(new ProcessPayrollAdjustmentCommand($c, $adjustment, $action, $this->user($r)->id, $r->string('reason')->toString() ?: null, $r->filled('corrected_gross_minor') ? $r->integer('corrected_gross_minor') : null, $r->filled('corrected_employee_insurance_minor') ? $r->integer('corrected_employee_insurance_minor') : null, $r->filled('corrected_employer_insurance_minor') ? $r->integer('corrected_employer_insurance_minor') : null, $r->filled('corrected_personal_income_tax_minor') ? $r->integer('corrected_personal_income_tax_minor') : null, $r->filled('corrected_net_minor') ? $r->integer('corrected_net_minor') : null));

        return back()->with('success', 'Payroll adjustment action completed: '.$action.'.');
    }

    public function downstream(PayrollAdjustmentRequest $r, int $adjustment, string $type): RedirectResponse
    {
        $permission = ['payment' => 'payment.create', 'accounting' => 'accounting.create', 'statement' => 'statement.generate'][$type] ?? abort(404);
        $company = $this->company($r, 'payroll.adjustment.'.$permission);
        $actor = $this->user($r)->id;
        $command = match ($type) {
            'payment' => new CreateAdjustmentPaymentInstructionCommand($company, $adjustment, $actor),
            'accounting' => new CreateAdjustmentAccountingJournalCommand($company, $adjustment, $actor),
            'statement' => new GenerateAdjustmentStatementCommand($company, $adjustment, $actor),
        };
        $this->commands->dispatch($command);

        return back()->with('success', ucfirst($type).' downstream processing completed.');
    }

    public function retry(PayrollAdjustmentRequest $r, int $adjustment, string $type): RedirectResponse
    {
        $company = $this->company($r, 'payroll.adjustment.downstream.retry');
        $downstreamType = PayrollAdjustmentDownstreamType::tryFrom($type);
        abort_if($downstreamType === null || $downstreamType === PayrollAdjustmentDownstreamType::Recovery, 404);
        $this->commands->dispatch(new RetryAdjustmentDownstreamCommand($company, $adjustment, $this->user($r)->id, $downstreamType));

        return back()->with('success', ucfirst($type).' downstream retry processed.');
    }

    public function downloadStatement(Request $r, int $adjustment): StreamedResponse
    {
        $company = $this->company($r, 'payroll.adjustment.statement.download');
        $response = $this->queries->ask(new DownloadAdjustmentStatementQuery($company, $adjustment, $this->user($r)->id));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }

    private function ask(GetPayrollAdjustmentQuery $q): PayrollAdjustmentViewModel
    {
        $v = $this->queries->ask($q);
        abort_unless($v instanceof PayrollAdjustmentViewModel, 500);

        return $v;
    }

    private function company(Request $r, string $p): int
    {
        $u = $this->user($r);
        $c = $r->integer('company_id');
        Gate::forUser($u)->authorize('workforce.company', [$p, $c]);

        return $c;
    }

    private function user(Request $r): User
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);

        return $u;
    }
}
