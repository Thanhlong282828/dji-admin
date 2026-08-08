<?php

namespace Modules\Employee\Application\DTOs;

final readonly class EmployeeLifecycleViewModel
{
    /**
     * @param  array<string, mixed>  $employee
     * @param  list<array<string, mixed>>  $timeline
     * @param  list<array<string, mixed>>  $employmentHistory
     * @param  list<array<string, mixed>>  $positionHistory
     * @param  list<array<string, mixed>>  $managerHistory
     */
    public function __construct(public array $employee, public string $currentState, public array $timeline, public array $employmentHistory, public array $positionHistory, public array $managerHistory) {}
}
