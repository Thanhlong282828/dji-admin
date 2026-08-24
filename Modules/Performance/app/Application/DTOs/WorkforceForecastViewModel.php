<?php

namespace Modules\Performance\Application\DTOs;

final readonly class WorkforceForecastViewModel
{
    /**
     * @param  array{current_headcount:int,approved_headcount:int,vacancy:int,open_requisition_vacancies:int,candidate_pipeline:int,candidate_to_vacancy_basis_points:int}  $headcount
     * @param  array{base_minor:int,kpi_minor:int,allowance_minor:int,target_gross_minor:int,currency:string}|null  $baselineCost
     * @param  array{headcount:int,base_minor:int,kpi_minor:int,allowance_minor:int,target_gross_minor:int,annualized_target_gross_minor:int}|null  $scenarioDelta
     * @param  array{A:array{headcount:int,base_minor:int,kpi_minor:int,allowance_minor:int,target_gross_minor:int,annualized_target_gross_minor:int},B:array{headcount:int,base_minor:int,kpi_minor:int,allowance_minor:int,target_gross_minor:int,annualized_target_gross_minor:int}}  $scenarioComparison
     * @param  list<array<string,int|string>>  $positions
     * @param  list<array<string,int|string>>  $units
     * @param  list<array<string,int|string>>  $projection
     * @param  list<string>  $assumptions
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $headcount,
        public ?array $baselineCost,
        public ?array $scenarioDelta,
        public array $scenarioComparison,
        public array $positions,
        public array $units,
        public array $projection,
        public array $assumptions,
        public array $warnings,
        public bool $salaryAllowed,
        public string $forecastMonth,
        public int $scenarioLineCount,
    ) {}
}
