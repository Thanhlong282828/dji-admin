<?php

namespace Modules\Attendance\Application\Queries\GetAttendanceDashboard;

use App\Services\WorkforceCacheRegistry;
use Modules\Attendance\Application\DTOs\AttendanceDashboard;
use Modules\Attendance\Domain\Models\AttendanceDailyRecord;
use Modules\Attendance\Domain\Models\AttendanceEvent;

final class GetAttendanceDashboardHandler
{
    public function __construct(private readonly WorkforceCacheRegistry $cache) {}

    public function handle(GetAttendanceDashboardQuery $query): AttendanceDashboard
    {
        $cached = $this->cache->rememberExternal($query->companyId, 'attendance', 'dashboard:'.today()->format('Y-m-d'), 60, fn (): AttendanceDashboard => $this->calculate($query));

        return $cached instanceof AttendanceDashboard ? $cached : $this->calculate($query);
    }

    private function calculate(GetAttendanceDashboardQuery $query): AttendanceDashboard
    {
        $balance = AttendanceEvent::query()->where('company_id', $query->companyId)->whereDate('local_date', today())->selectRaw("SUM(CASE WHEN event_type IN ('check_in','manual_in') THEN 1 WHEN event_type IN ('check_out','manual_out') THEN -1 ELSE 0 END) AS balance")->value('balance');

        return new AttendanceDashboard(
            AttendanceEvent::query()->where('company_id', $query->companyId)->whereDate('local_date', today())->count(),
            is_numeric($balance) ? (int) $balance : 0,
            (int) \DB::table('attendance_adjustment_requests')->where('company_id', $query->companyId)->where('status', 'pending')->count(),
            AttendanceDailyRecord::query()->where('company_id', $query->companyId)->where('calculation_status', 'anomaly')->count(),
        );
    }
}
