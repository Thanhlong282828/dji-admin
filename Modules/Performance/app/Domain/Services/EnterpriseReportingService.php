<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Performance\Application\DTOs\EnterpriseReportViewModel;
use Modules\Performance\Application\DTOs\ReportingFilter;

final readonly class EnterpriseReportingService
{
    public function __construct(
        private EnterpriseAnalyticsService $analytics,
        private PlanningExecutionService $execution,
        private EnterpriseReportCatalog $catalog,
    ) {}

    public function build(ReportingFilter $filter, bool $salaryAllowed): EnterpriseReportViewModel
    {
        $analytics = $this->analytics->build($filter->analytics(), $salaryAllowed);
        $execution = $this->execution->build($filter->execution(), $salaryAllowed);
        $quality = is_array($analytics['quality'] ?? null) ? $analytics['quality'] : [];
        $score = is_numeric($quality['score'] ?? null) ? (int) $quality['score'] : 0;
        $organization = is_array($analytics['organization'] ?? null) ? $analytics['organization'] : [];
        $planning = $execution->summary;
        $planningUnits = $execution->units;
        if (! $salaryAllowed) {
            $organization = array_map(static function (mixed $row): mixed {
                if (is_array($row)) {
                    unset($row['budget_minor']);
                }

                return $row;
            }, $organization);
            $planning = array_filter($planning, static fn (string $key): bool => ! str_contains($key, 'payroll'), ARRAY_FILTER_USE_KEY);
            $planningUnits = array_map(static function (array $row): array {
                foreach (array_keys($row) as $key) {
                    if (str_contains((string) $key, 'payroll')) {
                        unset($row[$key]);
                    }
                }

                return $row;
            }, $planningUnits);
        }
        $sections = [
            'executive' => $analytics['executive'] ?? [],
            'workforce' => $analytics['workforce'] ?? [],
            'attendance' => $analytics['attendance'] ?? [],
            'leave' => $analytics['leave'] ?? [],
            'recruitment' => $analytics['recruitment'] ?? [],
            'organization' => $organization,
            'workflow' => $analytics['workflow'] ?? [],
            'iam' => $analytics['iam'] ?? [],
            'quality' => $quality,
            'planning' => $planning,
            'planning_units' => $planningUnits,
        ];
        if ($salaryAllowed) {
            $sections['payroll'] = $analytics['payroll'] ?? [];
            $sections['compensation'] = $analytics['compensation'] ?? [];
        }
        $notes = $score < 100 ? ['Source data quality issue detected. Fix findings in the owning menu before management review.'] : ['Source Data Quality gate passed for the selected live report.'];
        $notes[] = 'Live report generated from current read models; it is not a persisted or certified artifact.';

        return new EnterpriseReportViewModel(
            self::stringValue(DB::table('companies')->where('id', $filter->companyId)->value('name'), 'Company'),
            $filter->periodType.' '.$filter->from->toDateString().' → '.$filter->to->toDateString(),
            now()->toIso8601String(),
            1,
            $salaryAllowed,
            $score,
            $sections,
            $this->catalog->all($salaryAllowed),
            $notes,
            ['cost_centers' => $this->options('cost_centers', $filter->companyId), 'units' => $this->options('organization_units', $filter->companyId), 'positions' => $this->positionOptions($filter->companyId)],
        );
    }

    /** @return list<array{id:int,name:string}> */
    private function options(string $table, int $companyId): array
    {
        return array_values(DB::table($table)->where('company_id', $companyId)->orderBy('name')->get(['id', 'name'])->map(static fn (object $row): array => ['id' => self::intValue($row->id ?? null), 'name' => self::stringValue($row->name ?? null)])->all());
    }

    /** @return list<array{id:int,name:string,level:int}> */
    private function positionOptions(int $companyId): array
    {
        return array_values(DB::table('job_positions')->where('company_id', $companyId)->orderBy('title')->get(['id', 'title', 'career_level'])->map(static fn (object $row): array => ['id' => self::intValue($row->id ?? null), 'name' => self::stringValue($row->title ?? null), 'level' => self::intValue($row->career_level ?? null)])->all());
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function stringValue(mixed $value, string $fallback = ''): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
