<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\SaveEmployeeTaxProfile\SaveEmployeeTaxProfileCommand;
use Modules\Payroll\Application\Commands\SaveTaxPolicy\SaveTaxPolicyCommand;
use Modules\Payroll\Application\DTOs\TaxAdministrationViewModel;
use Modules\Payroll\Application\Queries\GetEmployeeTaxProfileDetail\GetEmployeeTaxProfileDetailQuery;
use Modules\Payroll\Application\Queries\GetEmployeeTaxProfileList\GetEmployeeTaxProfileListQuery;
use Modules\Payroll\Application\Queries\GetTaxPolicyDetail\GetTaxPolicyDetailQuery;
use Modules\Payroll\Application\Queries\GetTaxPolicyList\GetTaxPolicyListQuery;
use Modules\Payroll\Presentation\Http\Requests\TaxAdministrationRequest;

final readonly class TaxAdministrationController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function policies(Request $r): View
    {
        $c = $this->company($r, 'payroll.tax.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'method' => (string) $r->query('method', ''), 'year' => (string) $r->query('year', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->queries->ask(new GetTaxPolicyListQuery($c, $f));
        abort_unless($vm instanceof TaxAdministrationViewModel, 500);

        return view('payroll::tax.policies.index', compact('c', 'f', 'vm'));
    }

    public function policyForm(Request $r, ?int $policy = null): View
    {
        $c = $this->company($r, $policy === null ? 'payroll.tax.policy.create' : 'payroll.tax.policy.update');
        $vm = $policy === null ? new TaxAdministrationViewModel : $this->queries->ask(new GetTaxPolicyDetailQuery($c, $policy));
        abort_unless($vm instanceof TaxAdministrationViewModel, 500);

        return view('payroll::tax.policies.form', compact('c', 'policy', 'vm'));
    }

    public function policy(Request $r, int $policy): View
    {
        $c = $this->company($r, 'payroll.tax.view');
        $vm = $this->queries->ask(new GetTaxPolicyDetailQuery($c, $policy));
        abort_unless($vm instanceof TaxAdministrationViewModel, 500);

        return view('payroll::tax.policies.show', compact('c', 'vm'));
    }

    public function savePolicy(TaxAdministrationRequest $r, ?int $policy = null): RedirectResponse
    {
        $c = $this->company($r, $policy === null ? 'payroll.tax.policy.create' : 'payroll.tax.policy.update');
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $id = $this->commands->dispatch(new SaveTaxPolicyCommand($c, $policy, $r->validated(), $u->id));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.tax.policies.show', ['policy' => $id, 'company_id' => $c])->with('success', 'Tax policy đã lưu.');
    }

    public function employees(Request $r): View
    {
        $c = $this->company($r, 'payroll.tax.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'residency' => (string) $r->query('residency', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->queries->ask(new GetEmployeeTaxProfileListQuery($c, $f));
        abort_unless($vm instanceof TaxAdministrationViewModel, 500);

        return view('payroll::tax.employees.index', compact('c', 'f', 'vm'));
    }

    public function employee(Request $r, int $employee): View
    {
        $c = $this->company($r, 'payroll.tax.view');
        $vm = $this->queries->ask(new GetEmployeeTaxProfileDetailQuery($c, $employee));
        abort_unless($vm instanceof TaxAdministrationViewModel, 500);

        return view('payroll::tax.employees.show', compact('c', 'employee', 'vm'));
    }

    public function saveProfile(TaxAdministrationRequest $r, int $employee, ?int $profile = null): RedirectResponse
    {
        $c = $this->company($r, 'payroll.tax.profile.manage');
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $this->commands->dispatch(new SaveEmployeeTaxProfileCommand($c, $employee, $profile, $r->validated(), $u->id));

        return back()->with('success', 'Employee tax profile đã lưu.');
    }

    private function company(Request $r, string $permission): int
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $c = $r->integer('company_id');
        Gate::forUser($u)->authorize('workforce.company', [$permission, $c]);

        return $c;
    }
}
