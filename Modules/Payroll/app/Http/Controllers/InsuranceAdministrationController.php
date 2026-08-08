<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Commands\SaveEmployeeInsuranceProfile\SaveEmployeeInsuranceProfileCommand;
use Modules\Payroll\Application\Commands\SaveInsurancePolicy\SaveInsurancePolicyCommand;
use Modules\Payroll\Application\DTOs\InsuranceAdministrationViewModel;
use Modules\Payroll\Application\Queries\GetEmployeeInsuranceProfileDetail\GetEmployeeInsuranceProfileDetailQuery;
use Modules\Payroll\Application\Queries\GetEmployeeInsuranceProfileList\GetEmployeeInsuranceProfileListQuery;
use Modules\Payroll\Application\Queries\GetInsurancePolicyDetail\GetInsurancePolicyDetailQuery;
use Modules\Payroll\Application\Queries\GetInsurancePolicyList\GetInsurancePolicyListQuery;
use Modules\Payroll\Presentation\Http\Requests\InsuranceAdministrationRequest;

final readonly class InsuranceAdministrationController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries) {}

    public function policies(Request $r): View
    {
        $c = $this->company($r, 'payroll.insurance.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'year' => (string) $r->query('year', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->queries->ask(new GetInsurancePolicyListQuery($c, $f));
        abort_unless($vm instanceof InsuranceAdministrationViewModel, 500);

        return view('payroll::insurance.policies.index', compact('c', 'f', 'vm'));
    }

    public function policyForm(Request $r, ?int $policy = null): View
    {
        $c = $this->company($r, $policy === null ? 'payroll.insurance.policy.create' : 'payroll.insurance.policy.update');
        $vm = $policy === null ? new InsuranceAdministrationViewModel : $this->queries->ask(new GetInsurancePolicyDetailQuery($c, $policy));
        abort_unless($vm instanceof InsuranceAdministrationViewModel, 500);

        return view('payroll::insurance.policies.form', compact('c', 'policy', 'vm'));
    }

    public function policy(Request $r, int $policy): View
    {
        $c = $this->company($r, 'payroll.insurance.view');
        $vm = $this->queries->ask(new GetInsurancePolicyDetailQuery($c, $policy));
        abort_unless($vm instanceof InsuranceAdministrationViewModel, 500);

        return view('payroll::insurance.policies.show', compact('c', 'vm'));
    }

    public function savePolicy(InsuranceAdministrationRequest $r, ?int $policy = null): RedirectResponse
    {
        $c = $this->company($r, $policy === null ? 'payroll.insurance.policy.create' : 'payroll.insurance.policy.update');
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $id = $this->commands->dispatch(new SaveInsurancePolicyCommand($c, $policy, $r->validated(), $u->id));
        abort_unless(is_int($id), 500);

        return redirect()->route('admin.payroll.insurance.policies.show', ['policy' => $id, 'company_id' => $c])->with('success', 'Insurance policy đã lưu.');
    }

    public function employees(Request $r): View
    {
        $c = $this->company($r, 'payroll.insurance.view');
        $f = ['search' => (string) $r->query('search', ''), 'status' => (string) $r->query('status', ''), 'page' => max(1, (int) $r->query('page', 1))];
        $vm = $this->queries->ask(new GetEmployeeInsuranceProfileListQuery($c, $f));
        abort_unless($vm instanceof InsuranceAdministrationViewModel, 500);

        return view('payroll::insurance.employees.index', compact('c', 'f', 'vm'));
    }

    public function employee(Request $r, int $employee): View
    {
        $c = $this->company($r, 'payroll.insurance.view');
        $vm = $this->queries->ask(new GetEmployeeInsuranceProfileDetailQuery($c, $employee));
        abort_unless($vm instanceof InsuranceAdministrationViewModel, 500);

        return view('payroll::insurance.employees.show', compact('c', 'employee', 'vm'));
    }

    public function saveProfile(InsuranceAdministrationRequest $r, int $employee, ?int $profile = null): RedirectResponse
    {
        $c = $this->company($r, 'payroll.insurance.profile.manage');
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $this->commands->dispatch(new SaveEmployeeInsuranceProfileCommand($c, $employee, $profile, $r->validated(), $u->id));

        return back()->with('success', 'Employee insurance profile đã lưu.');
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
