<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class OvertimeManagementViewModel
{
    /**
     * @param  list<array<string, bool|int|string|null>>  $policies
     * @param  list<array<string, int|string|null>>  $assignments
     * @param  list<array<string, int|string|null>>  $requests
     * @param  list<array{id:int,employee_code:string,name:string}>  $employees
     */
    public function __construct(public array $policies, public array $assignments, public array $requests, public array $employees) {}
}
