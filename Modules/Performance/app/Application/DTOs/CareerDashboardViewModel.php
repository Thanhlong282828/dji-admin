<?php

namespace Modules\Performance\Application\DTOs;

final readonly class CareerDashboardViewModel
{
    /**
     * @param  list<array<string, mixed>>  $paths
     * @param  list<array<string, mixed>>  $plans
     * @param  list<array<string, mixed>>  $succession
     * @param  list<array<string, mixed>>  $employees
     * @param  list<array<string, mixed>>  $positions
     */
    public function __construct(public array $paths, public array $plans, public array $succession, public array $employees, public array $positions, public int $readyNow, public int $criticalPositions) {}
}
