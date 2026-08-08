<?php

namespace Modules\Leave\Application\DTOs;

final readonly class LeaveManagementViewModel
{
    /**
     * @param  list<array<string,int|string|null>>  $policies
     * @param  list<array<string,int|string|null>>  $assignments
     * @param  list<array<string,int|string|null>>  $balances
     * @param  list<array{id:int,employee_code:string,name:string}>  $employees
     * @param  list<array{id:int,code:string,name:string}>  $types
     */
    public function __construct(public array $policies, public array $assignments, public array $balances, public array $employees, public array $types) {}
}
