<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayrollOvertimeClassification
{
    /** @param list<array{date:string,minutes:int,classification:string,reason:string}> $segments */
    public function __construct(public int $weekdayMinutes, public int $weekendMinutes, public int $holidayMinutes, public int $nightMinutes, public string $classificationSource, public string $classificationReason, public array $segments, public ?string $issueCode = null) {}

    public function total(): int
    {
        return $this->weekdayMinutes + $this->weekendMinutes + $this->holidayMinutes;
    }
}
