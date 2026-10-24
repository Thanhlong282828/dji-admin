<?php

namespace Modules\Performance\Application\DTOs;

final readonly class ExecutivePlanningViewModel
{
    /**
     * @param  array<string,int|string>  $summary
     * @param  list<array<string,int|string|null>>  $positions
     * @param  list<array<string,int|string>>  $units
     * @param  list<array<string,int|string>>  $costCenters
     * @param  list<array<string,int|string|null>>  $hiring
     * @param  list<string>  $warnings
     * @param  list<string>  $assumptions
     * @param  array{cost_centers:list<array{id:int,name:string}>,units:list<array{id:int,name:string}>,positions:list<array{id:int,name:string,unit_id:int,level:int}>}  $options
     */
    public function __construct(
        public array $summary,
        public array $positions,
        public array $units,
        public array $costCenters,
        public array $hiring,
        public array $warnings,
        public array $assumptions,
        public array $options,
        public bool $salaryAllowed,
        public string $period,
    ) {}
}
