<?php

namespace Modules\Attendance\Application\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class RecalculateOpenAttendanceRecordsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        DB::table('attendance_records')->whereIn('calculation_status', ['pending', 'calculated'])->where('is_locked', false)->whereDate('work_date', '>=', today()->subDay())->orderBy('id')->chunkById(200, function ($records): void {
            foreach ($records as $record) {
                if (! is_numeric($record->id ?? null) || ! is_numeric($record->company_id ?? null) || ! is_numeric($record->employee_id ?? null)) {
                    continue;
                }
                if (! is_string($record->work_date ?? null)) {
                    continue;
                }
                $events = DB::table('attendance_events')->where('company_id', (int) $record->company_id)->where('employee_id', (int) $record->employee_id)->whereDate('local_date', $record->work_date)->whereNull('invalidated_at')->orderBy('occurred_at')->get();
                $in = null;
                $worked = 0;
                $anomalies = [];
                foreach ($events as $event) {
                    $type = is_string($event->event_type ?? null) ? $event->event_type : '';
                    $at = is_string($event->occurred_at ?? null) ? CarbonImmutable::parse($event->occurred_at) : null;
                    if (in_array($type, ['check_in', 'manual_in'], true)) {
                        $in = $at;
                    } elseif (in_array($type, ['check_out', 'manual_out'], true) && $in !== null && $at !== null) {
                        $worked += (int) $in->diffInMinutes($at);
                        $in = null;
                    }
                }if ($in !== null) {
                    $anomalies[] = 'missing_check_out';
                }DB::table('attendance_records')->where('id', (int) $record->id)->update(['worked_minutes' => $worked, 'worked_hours' => number_format($worked / 60, 2, '.', ''), 'anomalies' => json_encode($anomalies), 'calculation_status' => $anomalies === [] ? 'calculated' : 'anomaly', 'calculation_version' => DB::raw('calculation_version + 1'), 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            }
        });
    }
}
