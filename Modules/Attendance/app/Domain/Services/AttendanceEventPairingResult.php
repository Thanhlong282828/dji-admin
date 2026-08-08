<?php

namespace Modules\Attendance\Domain\Services;

final readonly class AttendanceEventPairingResult
{
    /**
     * @param  list<array{in:string,out:string,minutes:int}>  $sessions
     * @param  list<string>  $anomalies
     */
    public function __construct(public array $sessions, public int $grossMinutes, public int $breakMinutes, public array $anomalies) {}
}
