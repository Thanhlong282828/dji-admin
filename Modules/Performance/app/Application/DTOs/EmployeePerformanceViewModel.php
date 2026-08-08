<?php

namespace Modules\Performance\Application\DTOs;

final readonly class EmployeePerformanceViewModel
{
    /**
     * @param  array<string, mixed>  $employee
     * @param  list<array<string, mixed>>  $goals
     * @param  list<array<string, mixed>>  $reviews
     * @param  list<array<string, mixed>>  $competencies
     * @param  list<array<string, mixed>>  $timeline
     */
    public function __construct(public array $employee, public array $goals, public array $reviews, public array $competencies, public array $timeline) {}
}
