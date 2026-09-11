<?php

namespace Modules\Attendance\Application\Jobs;

use App\Support\Employee\EmployeeBranchResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class GenerateAttendanceDailyRecordsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function handle(EmployeeBranchResolver $branches): void
    {
        DB::table('employee_work_profiles')->where('attendance_required', true)->whereDate('effective_from', '<=', today())->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->orderBy('id')->chunkById(200, function ($profiles) use ($branches): void {
            $byCompany = $profiles->groupBy('company_id');
            $branchIds = [];
            foreach ($byCompany as $companyId => $companyProfiles) {
                if (! is_numeric($companyId)) {
                    continue;
                }
                $employeeIds = $companyProfiles->pluck('employee_id')->filter(static fn (mixed $id): bool => is_numeric($id))->map(static fn (mixed $id): int => (int) $id)->values()->all();
                $branchIds[(int) $companyId] = $branches->resolveMany((int) $companyId, $employeeIds, CarbonImmutable::today());
            }
            foreach ($profiles as $profile) {
                if (! is_numeric($profile->company_id ?? null) || ! is_numeric($profile->employee_id ?? null) || ! is_numeric($profile->work_calendar_id ?? null)) {
                    continue;
                }
                $timezone = is_string($profile->timezone ?? null) ? $profile->timezone : 'UTC';
                $weekday = now($timezone)->dayOfWeekIso;
                $day = DB::table('work_calendar_days')->where('work_calendar_id', (int) $profile->work_calendar_id)->where('weekday', $weekday)->first();
                if (! is_object($day) || ! ($day->is_working_day ?? false)) {
                    continue;
                }
                $shiftId = DB::table('employee_shift_assignments')->where('company_id', (int) $profile->company_id)->where('employee_id', (int) $profile->employee_id)->where('is_primary', true)->whereDate('effective_from', '<=', today())->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->latest('effective_from')->value('work_shift_id');
                $shiftId = is_numeric($shiftId) ? (int) $shiftId : (is_numeric($profile->default_shift_id ?? null) ? (int) $profile->default_shift_id : (is_numeric($day->default_shift_id ?? null) ? (int) $day->default_shift_id : null));
                $branchId = $branchIds[(int) $profile->company_id][(int) $profile->employee_id] ?? null;
                DB::table('attendance_records')->insertOrIgnore(['company_id' => (int) $profile->company_id, 'employee_id' => (int) $profile->employee_id, 'branch_id' => $branchId, 'work_shift_id' => $shiftId, 'work_date' => today(), 'source' => 'system', 'scheduled_minutes' => is_numeric($day->planned_minutes ?? null) ? (int) $day->planned_minutes : 0, 'attendance_status' => 'incomplete', 'presence_status' => 'unapproved', 'approval_status' => 'draft', 'calculation_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
