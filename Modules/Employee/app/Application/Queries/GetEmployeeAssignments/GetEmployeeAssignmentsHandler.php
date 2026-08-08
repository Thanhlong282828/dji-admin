<?php

namespace Modules\Employee\Application\Queries\GetEmployeeAssignments;

use Illuminate\Database\Eloquent\Collection;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;

final class GetEmployeeAssignmentsHandler
{
    /** @return Collection<int, EmployeeAssignment> */
    public function handle(GetEmployeeAssignmentsQuery $query): Collection
    {
        Employee::query()->where('company_id', $query->companyId)->findOrFail($query->employeeId);

        return EmployeeAssignment::query()
            ->with(['organizationUnit:id,code,name', 'jobPosition:id,code,title', 'manager:id,employee_code,full_name'])
            ->where('employee_id', $query->employeeId)
            ->orderByDesc('effective_from')
            ->get();
    }
}
