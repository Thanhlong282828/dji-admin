<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\SaveAccountingAdministration\SaveAccountingAdministrationCommand;
use Modules\Payroll\Application\DTOs\AccountingViewModel;
use Modules\Payroll\Application\Queries\GetAccountingView\GetAccountingViewQuery;
use Modules\Payroll\Presentation\Http\Requests\AccountingAdministrationRequest;

final readonly class AccountingMappingController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function index(Request $r): View
    {
        $c = $this->company($r, 'payroll.accounting.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->ask(new GetAccountingViewQuery($c, 'mappings', filters: $f));

        return view('payroll::accounting.mappings.index', compact('c', 'f', 'vm'));
    }

    public function create(Request $r): View
    {
        $c = $this->company($r, 'payroll.accounting.mapping.manage');
        $vm = $this->ask(new GetAccountingViewQuery($c, 'mapping'));

        return view('payroll::accounting.mappings.form', compact('c', 'vm'));
    }

    public function edit(Request $r, int $mapping): View
    {
        $c = $this->company($r, 'payroll.accounting.mapping.manage');
        $vm = $this->ask(new GetAccountingViewQuery($c, 'mapping', $mapping));

        return view('payroll::accounting.mappings.form', compact('c', 'vm'));
    }

    public function store(AccountingAdministrationRequest $r): RedirectResponse
    {
        return $this->save($r, null);
    }

    public function update(AccountingAdministrationRequest $r, int $mapping): RedirectResponse
    {
        return $this->save($r, $mapping);
    }

    private function save(AccountingAdministrationRequest $r, ?int $id): RedirectResponse
    {
        $c = $this->company($r, 'payroll.accounting.mapping.manage');
        $data = $r->validated();
        unset($data['company_id']);
        $result = $this->commands->dispatch(new SaveAccountingAdministrationCommand($c, 'mapping', $id, $data, $this->user($r)->id));
        abort_unless(is_int($result), 500);

        return redirect()->route('admin.payroll.accounting.mappings.index', ['company_id' => $c])->with('success', 'Accounting Mapping saved.');
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
