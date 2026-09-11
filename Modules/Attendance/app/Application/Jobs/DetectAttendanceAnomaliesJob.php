<?php

namespace Modules\Attendance\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class DetectAttendanceAnomaliesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function handle(): void
    {
        DB::table('attendance_records')->where('is_locked', false)->whereDate('work_date', '<', today())->whereNull('check_in_at')->update(['attendance_status' => 'absent', 'presence_status' => 'absent', 'calculation_status' => 'anomaly', 'anomalies' => json_encode(['missing_check_in']), 'updated_at' => now()]);
    }
}
