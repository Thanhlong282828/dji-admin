<?php

namespace Modules\Employee\Application\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\Employee\Domain\Events\EmployeeAssignmentChanged;

final class EmployeeAssignmentChangeListener
{
    public function handle(EmployeeAssignmentChanged $event): void
    {
        Cache::increment('schema_admin:employee:'.$event->companyId.':version');
        Cache::increment('schema_admin:organization:'.$event->companyId.':version');
        activity('employee-assignment')
            ->event($event->action)
            ->withProperties([
                'company_id' => $event->companyId,
                'employee_id' => $event->employeeId,
                'assignment_id' => $event->assignmentId,
            ])
            ->log('Employee assignment '.$event->action);
    }
}
