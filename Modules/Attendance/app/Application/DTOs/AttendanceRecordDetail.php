<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class AttendanceRecordDetail
{
    /**
     * @param  list<string>  $anomalies
     * @param  list<array{id:int,occurred_at:string,event_type:string,source:string}>  $events
     * @param  list<array<string, int|string|null>>  $adjustments
     */
    public function __construct(public int $id, public int $companyId, public int $employeeId, public string $employee, public string $workDate, public ?string $checkIn, public ?string $checkOut, public int $scheduledMinutes, public int $workedMinutes, public int $breakMinutes, public string $calculationStatus, public string $approvalStatus, public bool $locked, public int $version, public array $anomalies, public array $events, public array $adjustments) {}
}
