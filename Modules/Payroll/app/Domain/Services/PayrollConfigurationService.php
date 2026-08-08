<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Events\PayrollConfigurationChanged;
use Modules\Payroll\Domain\Models\EmployeeSalaryComponentAssignment;
use Modules\Payroll\Domain\Models\SalaryComponent;

final readonly class PayrollConfigurationService
{
    public function __construct(private SalaryComponentValidator $validator) {}

    /** @param array<string,bool|int|string|null> $data */
    public function createComponent(int $company, array $data, int $actor): SalaryComponent
    {
        return DB::transaction(function () use ($company, $data, $actor): SalaryComponent {
            $this->validator->validate($data);
            $code = strtoupper(trim($this->text($data, 'code')));
            if (SalaryComponent::query()->where('company_id', $company)->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => 'Component code đã tồn tại.']);
            }$component = SalaryComponent::query()->create(array_merge($data, ['company_id' => $company, 'code' => $code, 'created_by' => $actor, 'updated_by' => $actor]));
            activity('payroll-configuration')->performedOn($component)->causedBy($actor)->event('created')->withProperties(['company_id' => $company])->log('Salary Component created');
            event(new PayrollConfigurationChanged($company, 'salary_component', $component->id, 'created', 1));

            return $component;
        }, 3);
    }

    /** @param array<string,bool|int|string|null> $data */
    public function updateComponent(int $company, int $id, array $data, int $expected, int $actor): SalaryComponent
    {
        return DB::transaction(function () use ($company, $id, $data, $expected, $actor): SalaryComponent {
            $component = SalaryComponent::query()->where('company_id', $company)->lockForUpdate()->findOrFail($id);
            if ((int) $component->version !== $expected) {
                throw ValidationException::withMessages(['version' => 'Component đã thay đổi.']);
            }if (DB::table('payroll_item_components')->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_item_components.payroll_run_id')->where('salary_component_id', $id)->where('payroll_runs.status', 'finalized')->exists() && array_intersect(['code', 'category', 'calculation_type', 'system_key'], array_keys($data)) !== []) {
                throw ValidationException::withMessages(['component' => 'Không thể đổi semantic đã finalized.']);
            }$rawType = $component->getRawOriginal('calculation_type');
            $this->validator->validate($data + ['calculation_type' => is_string($rawType) ? $rawType : '']);
            $component->update(array_merge($data, ['updated_by' => $actor, 'version' => $expected + 1]));
            event(new PayrollConfigurationChanged($company, 'salary_component', $id, 'updated', $expected + 1));

            return $component->refresh();
        }, 3);
    }

    public function statusComponent(int $company, int $id, string $status, int $expected, int $actor): SalaryComponent
    {
        return DB::transaction(function () use ($company, $id, $status, $expected, $actor): SalaryComponent {
            $component = SalaryComponent::query()->where('company_id', $company)->lockForUpdate()->findOrFail($id);
            if ((int) $component->version !== $expected) {
                throw ValidationException::withMessages(['version' => 'Component đã thay đổi.']);
            }$component->update(['status' => $status, 'updated_by' => $actor, 'version' => $expected + 1]);
            event(new PayrollConfigurationChanged($company, 'salary_component', $id, $status, $expected + 1));

            return $component->refresh();
        }, 3);
    }

    /** @param array<string,int|string|null> $data */
    public function assign(int $company, array $data, int $actor): EmployeeSalaryComponentAssignment
    {
        return DB::transaction(function () use ($company, $data, $actor): EmployeeSalaryComponentAssignment {
            $employee = (int) ($data['employee_id'] ?? 0);
            $component = (int) ($data['salary_component_id'] ?? 0);
            if (! DB::table('employees')->where('company_id', $company)->where('id', $employee)->lockForUpdate()->exists()) {
                abort(404);
            }
            SalaryComponent::query()->where('company_id', $company)->where('status', 'active')->lockForUpdate()->findOrFail($component);
            $from = $this->text($data, 'effective_from');
            $rawTo = $data['effective_to'] ?? null;
            $to = is_string($rawTo) ? $rawTo : null;
            $overlap = EmployeeSalaryComponentAssignment::query()->where('company_id', $company)->where('employee_id', $employee)->where('salary_component_id', $component)->where('status', 'active')->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))->when(is_string($to), fn ($q) => $q->whereDate('effective_from', '<=', $to))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['assignment' => 'Assignment effective range bị overlap.']);
            }$assignment = EmployeeSalaryComponentAssignment::query()->create(array_merge($data, ['company_id' => $company, 'created_by' => $actor, 'updated_by' => $actor]));
            activity('payroll-configuration')->performedOn($assignment)->causedBy($actor)->event('assigned')->withProperties(['company_id' => $company])->log('Salary Component assigned');
            event(new PayrollConfigurationChanged($company, 'salary_component_assignment', $assignment->id, 'assigned', 1));

            return $assignment;
        }, 3);
    }

    public function endAssignment(int $company, int $id, string $date, int $expected, int $actor): EmployeeSalaryComponentAssignment
    {
        return DB::transaction(function () use ($company, $id, $date, $expected, $actor): EmployeeSalaryComponentAssignment {
            $assignment = EmployeeSalaryComponentAssignment::query()->where('company_id', $company)->lockForUpdate()->findOrFail($id);
            if ((int) $assignment->version !== $expected) {
                throw ValidationException::withMessages(['version' => 'Assignment đã thay đổi.']);
            }if ($date < $assignment->effective_from->format('Y-m-d')) {
                throw ValidationException::withMessages(['effective_to' => 'End date không hợp lệ.']);
            }$assignment->update(['effective_to' => $date, 'status' => 'ended', 'updated_by' => $actor, 'version' => $expected + 1]);
            event(new PayrollConfigurationChanged($company, 'salary_component_assignment', $id, 'ended', $expected + 1));

            return $assignment->refresh();
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $v = $data[$key] ?? null;

        return is_string($v) ? $v : '';
    }
}
