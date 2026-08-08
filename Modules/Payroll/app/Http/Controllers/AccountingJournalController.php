<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\ExecuteAccountingJournal\ExecuteAccountingJournalCommand;
use Modules\Payroll\Application\Commands\ReverseAccountingJournal\ReverseAccountingJournalCommand;
use Modules\Payroll\Application\DTOs\AccountingViewModel;
use Modules\Payroll\Application\Queries\DownloadAccountingJournal\DownloadAccountingJournalQuery;
use Modules\Payroll\Application\Queries\GetAccountingView\GetAccountingViewQuery;
use Modules\Payroll\Presentation\Http\Requests\AccountingJournalRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AccountingJournalController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'payroll.accounting.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->ask(new GetAccountingViewQuery($c, 'journals', filters: $f));

        return view('payroll::accounting.journals.index', compact('c', 'f', 'vm'));
    }

    public function create(Request $r): View
    {
        $c = $this->company($r, 'payroll.accounting.journal.create');
        $vm = $this->ask(new GetAccountingViewQuery($c, 'mapping'));

        return view('payroll::accounting.journals.create', compact('c', 'vm'));
    }

    public function store(AccountingJournalRequest $r): RedirectResponse
    {
        $c = $this->company($r, 'payroll.accounting.journal.create');
        $id = $this->commands->dispatch(new ExecuteAccountingJournalCommand($c, 'create', null, $r->integer('run_id'), $r->string('posting_date')->toString(), $this->user($r)->id, idempotencyKey: $r->string('idempotency_key')->toString()));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.accounting.journals.show', ['journal' => $id, 'company_id' => $c])->with('success', 'Accounting Journal created from persisted finalized Payroll source.');
    }

    public function show(Request $r, int $journal): View
    {
        $c = $this->company($r, 'payroll.accounting.view');
        $vm = $this->ask(new GetAccountingViewQuery($c, 'journal', $journal));

        return view('payroll::accounting.journals.show', compact('c', 'vm'));
    }

    public function mutate(AccountingJournalRequest $r, int $journal, string $action): RedirectResponse
    {
        $permissions = ['validate' => 'payroll.accounting.journal.review', 'review' => 'payroll.accounting.journal.review', 'approve' => 'payroll.accounting.journal.approve', 'reject' => 'payroll.accounting.journal.approve', 'post' => 'payroll.accounting.journal.post', 'export' => 'payroll.accounting.journal.export'];
        $permission = $permissions[$action] ?? abort(404);
        $c = $this->company($r, $permission);
        $this->commands->dispatch(new ExecuteAccountingJournalCommand($c, $action, $journal, null, null, $this->user($r)->id, $r->string('reason')->toString() ?: null));

        return back()->with('success', 'Accounting Journal action completed: '.$action.'.');
    }

    public function reverse(AccountingJournalRequest $r, int $journal): RedirectResponse
    {
        $c = $this->company($r, 'payroll.accounting.journal.reverse');
        $id = $this->commands->dispatch(new ReverseAccountingJournalCommand($c, $journal, $this->user($r)->id, $r->string('reason')->toString(), $r->string('idempotency_key')->toString()));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.accounting.journals.show', ['journal' => $id, 'company_id' => $c])->with('success', 'Reversal Journal created in draft.');
    }

    public function download(Request $r, int $journal): StreamedResponse
    {
        $c = $this->company($r, 'payroll.accounting.journal.download');
        $response = $this->queries->ask(new DownloadAccountingJournalQuery($c, $journal, $this->user($r)->id));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }

    private function ask(GetAccountingViewQuery $q): AccountingViewModel
    {
        $vm = $this->queries->ask($q);
        abort_unless($vm instanceof AccountingViewModel, 500);

        return $vm;
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
