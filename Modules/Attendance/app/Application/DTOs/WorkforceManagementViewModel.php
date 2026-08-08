<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class WorkforceManagementViewModel
{
    /**
     * @param  list<array<string, bool|int|string|null>>  $rows
     * @param  list<array{id:int, code:string, name:string}>  $calendars
     * @param  list<array{id:int, code:string, name:string}>  $shifts
     * @param  list<array{id:int, employee_code:string, name:string}>  $employees
     */
    public function __construct(
        public string $section,
        public array $rows,
        public array $calendars,
        public array $shifts,
        public array $employees,
    ) {}
}
