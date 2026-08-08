<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Payroll\Application\Commands\AssignSalaryComponent\AssignSalaryComponentCommand;
use Modules\Payroll\Application\Commands\EndSalaryComponentAssignment\EndSalaryComponentAssignmentCommand;
use Modules\Payroll\Presentation\Http\Requests\PayrollConfigurationRequest;

final readonly class SalaryComponentAssignmentController
{
    public function __construct(private CommandBusInterface $commands) {}

    public function execute(PayrollConfigurationRequest $request, string $action, ?int $assignment = null): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        Gate::forUser($user)->authorize('workforce.company', ['payroll.assignment.manage', $companyId]);

        if ($action === 'assignment-store') {
            $employeeId = $request->integer('employee_id');
            $componentId = $request->integer('salary_component_id');
            Gate::authorize('salary-assignment.assign-record', [$companyId, $employeeId, $componentId]);
            $this->commands->dispatch(new AssignSalaryComponentCommand($companyId, $this->assignmentData($request), $user->id));
        } else {
            abort_unless($assignment !== null, 404);
            Gate::authorize('salary-assignment.end-record', [$companyId, $assignment]);
            $this->commands->dispatch(new EndSalaryComponentAssignmentCommand(
                $companyId,
                $assignment,
                $request->string('effective_to')->toString(),
                $request->integer('version'),
                $user->id,
            ));
        }

        return back()->with('success', 'Phân bổ thành phần lương đã cập nhật.');
    }

    /** @return array<string, int|string|null> */
    private function assignmentData(PayrollConfigurationRequest $request): array
    {
        return [
            'employee_id' => $request->integer('employee_id'),
            'salary_component_id' => $request->integer('salary_component_id'),
            'amount_minor' => $request->filled('amount_minor') ? $request->integer('amount_minor') : null,
            'currency_code' => $request->filled('currency_code') ? strtoupper($request->string('currency_code')->toString()) : null,
            'percentage_basis_points' => $request->filled('percentage_basis_points') ? $request->integer('percentage_basis_points') : null,
            'quantity_basis_points' => $request->filled('quantity_basis_points') ? $request->integer('quantity_basis_points') : null,
            'rate_minor' => $request->filled('rate_minor') ? $request->integer('rate_minor') : null,
            'effective_from' => $request->string('effective_from')->toString(),
            'effective_to' => $request->filled('effective_to') ? $request->string('effective_to')->toString() : null,
            'source' => $request->string('source')->toString(),
            'idempotency_key' => $request->string('idempotency_key')->toString(),
        ];
    }
}
