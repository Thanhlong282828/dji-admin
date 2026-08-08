<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\Commands\CreateEmployee\CreateEmployeeCommand;
use Modules\Employee\Application\Commands\DeleteEmployee\DeleteEmployeeCommand;
use Modules\Employee\Application\Commands\RestoreEmployee\RestoreEmployeeCommand;
use Modules\Employee\Application\Commands\UpdateEmployee\UpdateEmployeeCommand;
use Modules\Employee\Application\Queries\GetEmployeeDetail\GetEmployeeDetailQuery;
use Modules\Employee\Application\Queries\GetEmployeeList\GetEmployeeListQuery;
use Modules\Employee\Policies\EmployeePolicy;
use Modules\Employee\Presentation\Http\Requests\StoreEmployeeRequest;

final class EmployeeController extends Controller
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private EmployeePolicy $policy) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('employee::employees.index', ['items' => $this->queries->ask(new GetEmployeeListQuery($c, $this->stringKeyed($r->only(['search', 'employment_status', 'employment_type', 'has_user', 'sort', 'direction', 'per_page'])))), 'companyId' => $c]);
    }

    public function create(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);

        return view('employee::employees.create', ['companyId' => $c]);
    }

    public function store(StoreEmployeeRequest $r): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);
        $e = $this->stringKeyed($this->commands->dispatch(new CreateEmployeeCommand($c, $this->stringKeyed($r->safe()->except('company_id')), $this->actor($r))));

        return to_route('admin.employees.show', ['employee' => $e['id'], 'company_id' => $c])->with('success', 'Đã tạo hồ sơ nhân viên.');
    }

    public function show(Request $r, int $employee): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('employee::employees.show', ['item' => $this->queries->ask(new GetEmployeeDetailQuery($c, $employee, (bool) $r->user()?->can('employee.view-sensitive'))), 'companyId' => $c]);
    }

    public function edit(Request $r, int $employee): View
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);

        return view('employee::employees.edit', ['item' => $this->queries->ask(new GetEmployeeDetailQuery($c, $employee, false)), 'companyId' => $c]);
    }

    public function update(StoreEmployeeRequest $r, int $employee): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);
        $this->commands->dispatch(new UpdateEmployeeCommand($c, $employee, $this->stringKeyed($r->safe()->except('company_id')), $this->actor($r)));

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function destroy(Request $r, int $employee): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'delete', $c);
        $this->commands->dispatch(new DeleteEmployeeCommand($c, $employee, $this->actor($r)));

        return to_route('admin.employees.index', ['company_id' => $c])->with('success', 'Đã lưu trữ hồ sơ nhân viên.');
    }

    public function restore(Request $r, int $employee): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'restore', $c);
        $this->commands->dispatch(new RestoreEmployeeCommand($c, $employee, $this->actor($r)));

        return back()->with('success', 'Đã khôi phục hồ sơ.');
    }

    private function company(Request $r): int
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422, 'Vui lòng chọn công ty.');

        return $c;
    }

    private function actor(Request $r): int
    {
        $identifier = $r->user()?->getAuthIdentifier();

        return is_int($identifier) || is_numeric($identifier) ? (int) $identifier : 0;
    }

    private function allow(Request $r, string $action, int $company): void
    {
        $user = $r->user();
        abort_unless($user && $this->policy->{$action}($user, $company), 403);
    }

    /** @return array<string, mixed> */
    private function stringKeyed(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }
}
