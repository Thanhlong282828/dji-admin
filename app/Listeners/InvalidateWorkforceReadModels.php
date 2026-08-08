<?php

namespace App\Listeners;

use App\Services\WorkforceCacheRegistry;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;
use Modules\Attendance\Domain\Events\WorkforceOvertimeChanged;
use Modules\Leave\Domain\Events\WorkforceLeaveChanged;
use Modules\Payroll\Domain\Events\PayrollConfigurationChanged;
use Modules\Payroll\Domain\Events\PayrollPeriodChanged;
use Modules\Payroll\Domain\Events\PayrollRunChanged;
use Modules\Payroll\Domain\Events\WorkforcePayrollInputChanged;

final readonly class InvalidateWorkforceReadModels
{
    public function __construct(private WorkforceCacheRegistry $cache) {}

    public function handle(WorkforceAttendanceChanged|WorkforceLeaveChanged|WorkforceOvertimeChanged|WorkforcePayrollInputChanged|PayrollRunChanged|PayrollConfigurationChanged|PayrollPeriodChanged $event): void
    {
        $scope = match (true) {
            $event instanceof WorkforceAttendanceChanged => $this->isWorkforceConfiguration($event->action) ? 'workforce' : 'attendance',
            $event instanceof WorkforceLeaveChanged => 'leave',
            $event instanceof WorkforceOvertimeChanged => 'overtime',
            $event instanceof PayrollRunChanged, $event instanceof PayrollConfigurationChanged => 'payroll-run',
            $event instanceof PayrollPeriodChanged => 'payroll-period',
            default => 'payroll-input',
        };

        $this->cache->invalidate($event->companyId, $scope);
        if ($scope !== 'payroll-input') {
            $this->cache->invalidate($event->companyId, 'payroll-input');
        }
    }

    private function isWorkforceConfiguration(string $action): bool
    {
        foreach (['calendar.', 'holiday.', 'shift.', 'assignment.', 'break.'] as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
