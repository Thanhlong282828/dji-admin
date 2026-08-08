<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Payroll\Application\Commands\ChangeSalaryComponentStatus\ChangeSalaryComponentStatusCommand;
use Modules\Payroll\Application\Commands\CreateSalaryComponent\CreateSalaryComponentCommand;
use Modules\Payroll\Application\Commands\UpdateSalaryComponent\UpdateSalaryComponentCommand;
use Modules\Payroll\Presentation\Http\Requests\PayrollConfigurationRequest;

final readonly class SalaryComponentController
{
    public function __construct(private CommandBusInterface $commands) {}

    public function execute(PayrollConfigurationRequest $r, string $action, ?int $component = null): RedirectResponse
    {
        $u = $r->user();
        abort_unless($u instanceof User, 401);
        $c = $r->integer('company_id');
        Gate::forUser($u)->authorize('workforce.company', ['payroll.component.manage', $c]);
        $ability = match ($action) {
            'store' => 'salary-component.create-record','update' => 'salary-component.update-record',default => 'salary-component.'.($r->string('status')->toString() === 'active' ? 'activate' : 'deactivate').'-record'
        };
        Gate::authorize($ability, $component === null ? [$c] : [$c, $component]);
        if ($action === 'store') {
            $data = $this->data($r);
            $this->commands->dispatch(new CreateSalaryComponentCommand($c, $data, $u->id));
        } elseif ($action === 'update') {
            $this->commands->dispatch(new UpdateSalaryComponentCommand($c, (int) $component, $this->data($r), $r->integer('version'), $u->id));
        } else {
            $this->commands->dispatch(new ChangeSalaryComponentStatusCommand($c, (int) $component, $r->string('status')->toString(), $r->integer('version'), $u->id));
        }

        return back()->with('success', 'Payroll component đã cập nhật.');
    }

    /** @return array<string,bool|int|string|null> */
    private function data(PayrollConfigurationRequest $r): array
    {
        return ['code' => $r->string('code')->toString(), 'name' => $r->string('name')->toString(), 'category' => $r->string('category')->toString(), 'insurance_status' => $r->filled('insurance_status') ? $r->string('insurance_status')->toString() : 'not_configured', 'calculation_type' => $r->string('calculation_type')->toString(), 'value_type' => $r->string('value_type')->toString(), 'default_value_minor' => $r->filled('default_value_minor') ? $r->integer('default_value_minor') : null, 'default_percentage_basis_points' => $r->filled('default_percentage_basis_points') ? $r->integer('default_percentage_basis_points') : null, 'currency_code' => $r->filled('currency_code') ? strtoupper($r->string('currency_code')->toString()) : null, 'affects_gross' => $r->boolean('affects_gross'), 'affects_net' => $r->boolean('affects_net'), 'status' => $r->string('status')->toString()];
    }
}
