<?php

namespace Modules\Payroll\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\PayrollOvertimeClassification;

final readonly class PayrollOvertimeClassificationService
{
    public function __construct(private ConnectionInterface $db) {}

    public function classify(int $companyId, int $employeeId, string $startsAt, string $endsAt, int $approvedMinutes): PayrollOvertimeClassification
    {
        if ($approvedMinutes < 0) {
            return new PayrollOvertimeClassification(0, 0, 0, 0, 'invalid', 'Negative approved minutes.', [], 'OVERTIME_INTERVAL_INVALID');
        }
        $start = CarbonImmutable::parse($startsAt);
        $end = CarbonImmutable::parse($endsAt);
        if ($end->lessThanOrEqualTo($start) || $start->diffInMinutes($end) < $approvedMinutes) {
            return new PayrollOvertimeClassification(0, 0, 0, 0, 'invalid', 'Approved minutes exceed the captured interval.', [], 'OVERTIME_INTERVAL_INVALID');
        }
        $remaining = $approvedMinutes;
        $cursor = $start;
        $weekday = $weekend = $holiday = 0;
        $segments = [];
        while ($remaining > 0 && $cursor->lessThan($end)) {
            $boundary = $cursor->addDay()->startOfDay();
            $segmentEnd = $boundary->lessThan($end) ? $boundary : $end;
            $minutes = min($remaining, (int) $cursor->diffInMinutes($segmentEnd));
            $date = $cursor->toDateString();
            $classification = $this->dayClassification($companyId, $employeeId, $date);
            if ($classification === null) {
                return new PayrollOvertimeClassification(0, 0, 0, 0, 'unresolved', 'No effective employee/company work calendar.', [], 'OVERTIME_CLASSIFICATION_UNRESOLVED');
            }
            if ($classification === 'weekday') {
                $weekday += $minutes;
            } elseif ($classification === 'weekend') {
                $weekend += $minutes;
            } else {
                $holiday += $minutes;
            }
            $segments[] = ['date' => $date, 'minutes' => $minutes, 'classification' => $classification, 'reason' => $classification === 'holiday' ? 'company_holiday' : 'effective_work_calendar'];
            $remaining -= $minutes;
            $cursor = $segmentEnd;
        }

        return new PayrollOvertimeClassification($weekday, $weekend, $holiday, 0, 'calendar', 'Company holiday then effective employee work calendar.', $segments, $remaining === 0 ? null : 'OVERTIME_MINUTES_MISMATCH');
    }

    private function dayClassification(int $companyId, int $employeeId, string $date): ?string
    {
        $profile = $this->db->table('employee_work_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $date)->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->orderByDesc('effective_from')->first();
        if (! is_object($profile) || ! is_numeric($profile->work_calendar_id ?? null)) {
            return null;
        }
        $calendarId = (int) $profile->work_calendar_id;
        $holiday = $this->db->table('company_holidays')->where('company_id', $companyId)->where('holiday_date', $date)->where(fn (Builder $q): Builder => $q->whereNull('work_calendar_id')->orWhere('work_calendar_id', $calendarId))->where('is_working_override', false)->exists();
        if ($holiday) {
            return 'holiday';
        }
        $working = $this->db->table('work_calendar_days')->where('work_calendar_id', $calendarId)->where('weekday', CarbonImmutable::parse($date)->dayOfWeekIso)->value('is_working_day');

        return $working === null ? null : ((bool) $working ? 'weekday' : 'weekend');
    }
}
