<?php

namespace Modules\Employee\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Employee\Application\DTOs\EmployeeLifecycleViewModel;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Models\EmployeeManagerHistory;
use Modules\Employee\Domain\Models\EmployeePositionHistory;
use Modules\Employee\Domain\Models\EmploymentTransition;
use Modules\Employee\Domain\Repositories\EmployeeLifecycleReadRepositoryInterface;

final class EloquentEmployeeLifecycleReadRepository implements EmployeeLifecycleReadRepositoryInterface
{
    public function paginate(int $companyId, ?string $search, ?string $state, int $perPage): LengthAwarePaginator
    {
        $query = Employee::query()->where('company_id', $companyId)->with('latestLifecycleEvent:employee_lifecycle_events.id,employee_lifecycle_events.employee_id,employee_lifecycle_events.to_state')->select(['id', 'employee_code', 'full_name', 'work_email', 'employment_status']);
        if ($search !== null && $search !== '') {
            $query->where(fn (Builder $q): Builder => $q->where('employee_code', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%"));
        }
        if ($state !== null && $state !== '') {
            $query->whereHas('latestLifecycleEvent', fn (Builder $q): Builder => $q->where('to_state', $state));
        }
        /** @var LengthAwarePaginator<int, array<string, mixed>> $result */
        $result = $query->orderBy('employee_code')->paginate(max(5, min(100, $perPage)))->through(fn (Employee $e): array => $this->listItem($e));

        return $result;
    }

    public function employee360(int $companyId, int $employeeId): EmployeeLifecycleViewModel
    {
        $employee = Employee::query()->where('company_id', $companyId)->with(['company:id,code,name'])->findOrFail($employeeId);
        $timeline = $this->timeline($companyId, $employeeId);
        $positions = [];
        foreach (EmployeePositionHistory::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->orderByDesc('effective_from')->get() as $row) {
            $positions[] = $this->stringKeyed($row->toArray());
        }
        $managers = [];
        foreach (EmployeeManagerHistory::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->orderByDesc('effective_from')->get() as $row) {
            $managers[] = $this->stringKeyed($row->toArray());
        }
        $state = $timeline[0]['to_state'] ?? 'not_started';

        return new EmployeeLifecycleViewModel($this->stringKeyed($employee->only(['id', 'company_id', 'employee_code', 'full_name', 'work_email', 'join_date', 'company'])), is_string($state) ? $state : 'not_started', $timeline, $this->employmentHistory($companyId, $employeeId), $positions, $managers);
    }

    public function employmentHistory(int $companyId, int $employeeId): array
    {
        $result = [];
        foreach (EmploymentTransition::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->latest('effective_at')->get() as $row) {
            $result[] = $this->stringKeyed($row->toArray());
        }

        return $result;
    }

    public function timeline(int $companyId, int $employeeId): array
    {
        $result = [];
        foreach (EmployeeLifecycleEvent::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->latest('effective_at')->get() as $row) {
            $result[] = $this->stringKeyed($row->toArray());
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function listItem(Employee $employee): array
    {
        $state = $employee->latestLifecycleEvent?->getRawOriginal('to_state');

        return ['id' => (int) $employee->id, 'employee_code' => (string) $employee->employee_code, 'full_name' => (string) $employee->full_name, 'work_email' => is_string($employee->work_email) ? $employee->work_email : null, 'lifecycle_state' => is_string($state) ? $state : 'not_started'];
    }

    /**
     * @param  array<mixed, mixed>  $source
     * @return array<string, mixed>
     */
    private function stringKeyed(array $source): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
