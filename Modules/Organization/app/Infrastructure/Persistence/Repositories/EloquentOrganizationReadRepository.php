<?php

namespace Modules\Organization\Infrastructure\Persistence\Repositories;

use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Application\DTOs\OrganizationExportData;
use Modules\Organization\Domain\Enums\OrganizationExportType;
use Modules\Organization\Domain\Models\CostCenter;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Organization\Domain\Repositories\OrganizationReadRepositoryInterface;
use Modules\Organization\Domain\Services\OrganizationTreeService;

final readonly class EloquentOrganizationReadRepository implements OrganizationReadRepositoryInterface
{
    public function __construct(private OrganizationTreeService $trees) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function costCenters(int $companyId, array $filters): LengthAwarePaginator
    {
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;
        $currency = is_string($filters['currency'] ?? null) ? $filters['currency'] : null;
        $sortValue = $filters['sort'] ?? 'created_at';
        $sort = is_string($sortValue) && in_array($sortValue, ['code', 'name', 'annual_budget', 'created_at'], true) ? $sortValue : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPageValue = $filters['per_page'] ?? 15;
        $perPage = is_int($perPageValue) || (is_string($perPageValue) && ctype_digit($perPageValue)) ? min((int) $perPageValue, 100) : 15;
        $query = CostCenter::query()->where('company_id', $companyId);
        if ($search !== null && $search !== '') {
            $query->where(fn ($nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }
        if ($currency !== null && $currency !== '') {
            $query->where('currency', $currency);
        }
        if (array_key_exists('has_budget', $filters)) {
            filter_var($filters['has_budget'], FILTER_VALIDATE_BOOL) ? $query->where('annual_budget', '>', 0) : $query->where('annual_budget', 0);
        }

        return $query->orderBy($sort, $direction)->paginate($perPage)->through(fn (CostCenter $model): array => $this->modelArray($model));
    }

    public function costCenter(int $companyId, int $id): array
    {
        return $this->modelArray(CostCenter::query()->where('company_id', $companyId)->findOrFail($id));
    }

    public function costCenterBudget(int $companyId): array
    {
        return ['total' => (float) CostCenter::query()->where('company_id', $companyId)->sum('annual_budget'), 'count' => CostCenter::query()->where('company_id', $companyId)->count()];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function units(int $companyId, array $filters): LengthAwarePaginator
    {
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;
        $type = is_string($filters['type'] ?? null) ? $filters['type'] : null;
        $parent = $filters['parent_id'] ?? null;
        $perPageValue = $filters['per_page'] ?? 15;
        $perPage = is_int($perPageValue) || (is_string($perPageValue) && ctype_digit($perPageValue)) ? min((int) $perPageValue, 100) : 15;
        $query = OrganizationUnit::query()->with('costCenter:id,code,name')->where('company_id', $companyId);
        if ($search !== null && $search !== '') {
            $query->where(fn ($nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }
        if (is_int($parent) || (is_string($parent) && ctype_digit($parent))) {
            $query->where('parent_id', (int) $parent);
        }
        if (filter_var($filters['active'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereDate('effective_from', '<=', today())->where(fn ($nested) => $nested->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()));
        }

        return $query->orderBy('name')->paginate($perPage)->through(fn (OrganizationUnit $model): array => $this->modelArray($model));
    }

    public function unit(int $companyId, int $id): array
    {
        $unit = OrganizationUnit::query()->with(['costCenter:id,code,name', 'children:id,parent_id,code,name,type'])->where('company_id', $companyId)->findOrFail($id);

        return [...$this->modelArray($unit), 'breadcrumb' => $this->breadcrumb($companyId, $id)];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function tree(int $companyId, array $filters = []): array
    {
        $rows = $this->headcountRows($companyId, $filters);

        return $this->trees->build($rows);
    }

    public function breadcrumb(int $companyId, int $id): array
    {
        $result = [];
        $cursor = $id;
        while ($cursor !== null) {
            $row = OrganizationUnit::query()->where('company_id', $companyId)->whereKey($cursor)->firstOrFail(['id', 'parent_id', 'code', 'name', 'type']);
            array_unshift($result, $this->modelArray($row));
            $parentId = $row->getAttribute('parent_id');
            $cursor = is_int($parentId) ? $parentId : null;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function positions(int $companyId, array $filters): LengthAwarePaginator
    {
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;
        $unit = $filters['organization_unit_id'] ?? null;
        $grade = is_string($filters['job_grade'] ?? null) ? $filters['job_grade'] : null;
        $perPageValue = $filters['per_page'] ?? 15;
        $perPage = is_int($perPageValue) || (is_string($perPageValue) && ctype_digit($perPageValue)) ? min((int) $perPageValue, 100) : 15;
        $query = JobPosition::query()->with('organizationUnit:id,code,name')->where('company_id', $companyId);
        if ($search !== null && $search !== '') {
            $query->where(fn ($nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        }
        if (is_int($unit) || (is_string($unit) && ctype_digit($unit))) {
            $query->where('organization_unit_id', (int) $unit);
        }
        if ($grade !== null && $grade !== '') {
            $query->where('job_grade', $grade);
        }
        if (filter_var($filters['critical_only'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where('is_critical', true);
        }

        return $query->orderBy('title')->paginate($perPage)->through(fn (JobPosition $model): array => $this->modelArray($model));
    }

    public function position(int $companyId, int $id): array
    {
        return $this->modelArray(JobPosition::query()->with('organizationUnit:id,code,name')->where('company_id', $companyId)->findOrFail($id));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(int $companyId, array $filters): array
    {
        $version = Cache::get('schema_admin:organization:'.$companyId.':version', 1);
        $key = 'schema_admin:organization:'.$companyId.':headcount-dashboard:'.hash('sha256', serialize([$version, $filters]));

        return Cache::remember($key, now()->addMinutes(10), function () use ($companyId, $filters): array {
            $positions = DB::table('job_positions')->where('company_id', $companyId);
            $assignment = DB::table('employee_assignments as ea')->join('job_positions as jp', 'jp.id', '=', 'ea.job_position_id')->where('jp.company_id', $companyId)->whereDate('ea.effective_from', '<=', today())->where(fn (Builder $query): Builder => $query->whereNull('ea.effective_to')->orWhereDate('ea.effective_to', '>=', today()));
            $people = (clone $assignment)->distinct()->count('ea.employee_id');
            $fte = (float) (clone $assignment)->sum(DB::raw('ea.allocation_percent / 100'));
            $approved = (int) (clone $positions)->sum('approved_headcount');

            return ['total_units' => DB::table('organization_units')->where('company_id', $companyId)->count(), 'total_cost_centers' => DB::table('cost_centers')->where('company_id', $companyId)->count(), 'total_positions' => (clone $positions)->count(), 'approved_headcount' => $approved, 'people_count' => $people, 'allocated_fte' => $fte, 'vacancy' => $approved - $fte, 'critical_positions' => (clone $positions)->where('is_critical', true)->count(), 'annual_budget' => (float) DB::table('cost_centers')->where('company_id', $companyId)->sum('annual_budget'), 'budgeted_position_cost' => (float) (clone $positions)->sum('budgeted_cost'), 'tree' => $this->tree($companyId, $filters)];
        });
    }

    /** @param array<string, mixed> $filters */
    public function export(int $companyId, OrganizationExportType $type, array $filters): OrganizationExportData
    {
        $date = now()->format('Y-m-d');

        return match ($type) {
            OrganizationExportType::COST_CENTERS => new OrganizationExportData(
                "cost-centers-{$date}.csv",
                ['Code', 'Name', 'Company', 'Parent Cost Center', 'Budget', 'Currency', 'Effective From', 'Effective To', 'Status', 'Created At'],
                $this->costCenterExportRows($companyId, $filters),
            ),
            OrganizationExportType::ORGANIZATION_UNITS => new OrganizationExportData(
                "organization-units-{$date}.csv",
                ['Code', 'Name', 'Type', 'Parent Unit', 'Depth', 'Path', 'Cost Center', 'Manager', 'Effective From', 'Effective To', 'Status'],
                $this->organizationUnitExportRows($companyId, $filters, false),
            ),
            OrganizationExportType::ORGANIZATION_TREE => new OrganizationExportData(
                "organization-tree-{$date}.csv",
                ['Depth', 'Path', 'Code', 'Name', 'Type', 'Parent Code', 'Cost Center', 'Active Headcount', 'Approved Headcount', 'Vacancy'],
                $this->organizationUnitExportRows($companyId, $filters, true),
            ),
            OrganizationExportType::JOB_POSITIONS => new OrganizationExportData(
                "job-positions-{$date}.csv",
                ['Code', 'Title', 'Organization Unit', 'Cost Center', 'Approved Headcount', 'Allocated FTE', 'Vacancy', 'Annual Budget', 'Budgeted Position Cost', 'Critical', 'Effective From', 'Effective To', 'Status'],
                $this->jobPositionExportRows($companyId, $filters),
            ),
            OrganizationExportType::HEADCOUNT => new OrganizationExportData(
                "headcount-report-{$date}.csv",
                ['Organization Unit', 'Job Position', 'Approved Headcount', 'People Count', 'Allocated FTE', 'Vacancy', 'Annual Budget', 'Budgeted Position Cost', 'Utilization Percent', 'Critical'],
                $this->headcountExportRows($companyId, $filters),
            ),
        };
    }

    /** @param array<string, mixed> $filters @return Generator<int, list<bool|float|int|string|null>, mixed, void> */
    private function costCenterExportRows(int $companyId, array $filters): Generator
    {
        $query = DB::table('cost_centers as cc')->join('companies as c', 'c.id', '=', 'cc.company_id')->where('cc.company_id', $companyId);
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;
        $currency = is_string($filters['currency'] ?? null) ? $filters['currency'] : null;
        if ($search !== null && $search !== '') {
            $query->where(fn (Builder $nested): Builder => $nested->where('cc.code', 'like', "%{$search}%")->orWhere('cc.name', 'like', "%{$search}%"));
        }
        if ($currency !== null && $currency !== '') {
            $query->where('cc.currency', $currency);
        }
        if (array_key_exists('has_budget', $filters)) {
            filter_var($filters['has_budget'], FILTER_VALIDATE_BOOL) ? $query->where('cc.annual_budget', '>', 0) : $query->where('cc.annual_budget', 0);
        }
        foreach ($query->orderBy('cc.code')->select(['cc.code', 'cc.name', 'c.name as company_name', 'cc.annual_budget', 'cc.currency', 'cc.created_at'])->cursor() as $row) {
            $values = get_object_vars($row);
            yield [
                $this->stringValue($values['code'] ?? ''),
                $this->stringValue($values['name'] ?? ''),
                $this->stringValue($values['company_name'] ?? ''),
                '',
                $this->stringValue($values['annual_budget'] ?? '0.00'),
                $this->stringValue($values['currency'] ?? ''),
                '',
                '',
                'Active',
                $this->stringValue($values['created_at'] ?? ''),
            ];
        }
    }

    /** @param array<string, mixed> $filters @return Generator<int, list<bool|float|int|string|null>, mixed, void> */
    private function organizationUnitExportRows(int $companyId, array $filters, bool $tree): Generator
    {
        $query = DB::table('organization_units as ou')->leftJoin('cost_centers as cc', 'cc.id', '=', 'ou.cost_center_id')->where('ou.company_id', $companyId);
        $type = is_string($filters['type'] ?? null) ? $filters['type'] : null;
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;
        if ($type !== null && $type !== '') {
            $query->where('ou.type', $type);
        }
        if ($search !== null && $search !== '') {
            $query->where(fn (Builder $nested): Builder => $nested->where('ou.code', 'like', "%{$search}%")->orWhere('ou.name', 'like', "%{$search}%"));
        }
        $records = $query->orderBy('ou.name')->get(['ou.id', 'ou.parent_id', 'ou.code', 'ou.name', 'ou.type', 'ou.effective_from', 'ou.effective_to', 'cc.code as cost_center_code']);
        $byId = [];
        foreach ($records as $record) {
            $values = get_object_vars($record);
            $normalized = [];
            foreach ($values as $key => $value) {
                $normalized[(string) $key] = $value;
            }
            $byId[$this->intValue($values['id'] ?? 0)] = $normalized;
        }
        $metrics = DB::table('job_positions as jp')
            ->leftJoin('employee_assignments as ea', fn (JoinClause $join): JoinClause => $join->on('ea.job_position_id', '=', 'jp.id')->whereDate('ea.effective_from', '<=', today())->where(fn (Builder $nested): Builder => $nested->whereNull('ea.effective_to')->orWhereDate('ea.effective_to', '>=', today())))
            ->where('jp.company_id', $companyId)
            ->groupBy('jp.organization_unit_id')
            ->selectRaw('jp.organization_unit_id, SUM(jp.approved_headcount) approved, COALESCE(SUM(ea.allocation_percent / 100),0) allocated_fte')
            ->get()
            ->keyBy('organization_unit_id');
        foreach ($byId as $values) {
            [$depth, $path, $parentCode] = $this->organizationPath($values, $byId);
            $id = $this->intValue($values['id'] ?? 0);
            $metric = $metrics->get($id);
            $metricValues = is_object($metric) ? get_object_vars($metric) : [];
            $approved = $this->intValue($metricValues['approved'] ?? 0);
            $fte = $this->floatValue($metricValues['allocated_fte'] ?? 0);
            if ($tree) {
                yield [$depth, $path, $this->stringValue($values['code'] ?? ''), $this->stringValue($values['name'] ?? ''), $this->stringValue($values['type'] ?? ''), $parentCode, $this->nullableStringValue($values['cost_center_code'] ?? null), $fte, $approved, $approved - $fte];

                continue;
            }
            $active = $values['effective_to'] === null || $this->stringValue($values['effective_to']) >= today()->toDateString();
            yield [$this->stringValue($values['code'] ?? ''), $this->stringValue($values['name'] ?? ''), $this->stringValue($values['type'] ?? ''), $parentCode, $depth, $path, $this->nullableStringValue($values['cost_center_code'] ?? null), '', $this->stringValue($values['effective_from'] ?? ''), $this->nullableStringValue($values['effective_to'] ?? null), $active ? 'Active' : 'Inactive'];
        }
    }

    /** @param array<string, mixed> $filters @return Generator<int, list<bool|float|int|string|null>, mixed, void> */
    private function jobPositionExportRows(int $companyId, array $filters): Generator
    {
        $query = $this->headcountExportQuery($companyId, $filters);
        foreach ($query->orderBy('jp.code')->cursor() as $row) {
            $values = get_object_vars($row);
            $approved = $this->intValue($values['approved_headcount'] ?? 0);
            $fte = $this->floatValue($values['allocated_fte'] ?? 0);
            yield [$this->stringValue($values['code'] ?? ''), $this->stringValue($values['title'] ?? ''), $this->stringValue($values['unit_name'] ?? ''), $this->nullableStringValue($values['cost_center_code'] ?? null), $approved, $fte, $approved - $fte, $this->stringValue($values['annual_budget'] ?? '0.00'), $this->stringValue($values['budgeted_cost'] ?? '0.00'), (bool) ($values['is_critical'] ?? false), $this->nullableStringValue($values['effective_from'] ?? null), $this->nullableStringValue($values['effective_to'] ?? null), 'Active'];
        }
    }

    /** @param array<string, mixed> $filters @return Generator<int, list<bool|float|int|string|null>, mixed, void> */
    private function headcountExportRows(int $companyId, array $filters): Generator
    {
        foreach ($this->headcountExportQuery($companyId, $filters)->orderBy('ou.name')->orderBy('jp.title')->cursor() as $row) {
            $values = get_object_vars($row);
            $approved = $this->intValue($values['approved_headcount'] ?? 0);
            $fte = $this->floatValue($values['allocated_fte'] ?? 0);
            $utilization = $approved > 0 ? round(($fte / $approved) * 100, 2) : 0.0;
            yield [$this->stringValue($values['unit_name'] ?? ''), $this->stringValue($values['title'] ?? ''), $approved, $this->intValue($values['people_count'] ?? 0), $fte, $approved - $fte, $this->stringValue($values['annual_budget'] ?? '0.00'), $this->stringValue($values['budgeted_cost'] ?? '0.00'), $utilization, (bool) ($values['is_critical'] ?? false)];
        }
    }

    /** @param array<string, mixed> $filters */
    private function headcountExportQuery(int $companyId, array $filters): Builder
    {
        $date = is_string($filters['effective_date'] ?? null) ? $filters['effective_date'] : today()->toDateString();
        $query = DB::table('job_positions as jp')
            ->join('organization_units as ou', 'ou.id', '=', 'jp.organization_unit_id')
            ->leftJoin('cost_centers as cc', 'cc.id', '=', 'ou.cost_center_id')
            ->leftJoin('employee_assignments as ea', fn (JoinClause $join): JoinClause => $join->on('ea.job_position_id', '=', 'jp.id')->whereDate('ea.effective_from', '<=', $date)->where(fn (Builder $nested): Builder => $nested->whereNull('ea.effective_to')->orWhereDate('ea.effective_to', '>=', $date)))
            ->where('jp.company_id', $companyId);
        $unit = $filters['organization_unit_id'] ?? null;
        if (is_int($unit) || (is_string($unit) && ctype_digit($unit))) {
            $query->where('jp.organization_unit_id', (int) $unit);
        }
        $position = $filters['job_position_id'] ?? null;
        if (is_int($position) || (is_string($position) && ctype_digit($position))) {
            $query->where('jp.id', (int) $position);
        }
        $grade = is_string($filters['job_grade'] ?? null) ? $filters['job_grade'] : null;
        if ($grade !== null && $grade !== '') {
            $query->where('jp.job_grade', $grade);
        }
        if (filter_var($filters['critical_only'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where('jp.is_critical', true);
        }

        return $query->groupBy(['jp.id', 'jp.code', 'jp.title', 'jp.approved_headcount', 'jp.budgeted_cost', 'jp.is_critical', 'ou.name', 'ou.effective_from', 'ou.effective_to', 'cc.code', 'cc.annual_budget'])
            ->selectRaw('jp.code, jp.title, jp.approved_headcount, jp.budgeted_cost, jp.is_critical, ou.name unit_name, ou.effective_from, ou.effective_to, cc.code cost_center_code, COALESCE(cc.annual_budget,0) annual_budget, COUNT(DISTINCT ea.employee_id) people_count, COALESCE(SUM(ea.allocation_percent / 100),0) allocated_fte');
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, array<string, mixed>>  $byId
     * @return array{int, string, string}
     */
    private function organizationPath(array $values, array $byId): array
    {
        $names = [$this->stringValue($values['name'] ?? '')];
        $parentCode = '';
        $parent = $values['parent_id'] ?? null;
        $visited = [];
        while ($parent !== null) {
            $parentId = $this->intValue($parent);
            if (isset($visited[$parentId]) || ! isset($byId[$parentId])) {
                break;
            }
            $visited[$parentId] = true;
            $parentValues = $byId[$parentId];
            $parentCode = $this->stringValue($parentValues['code'] ?? '');
            array_unshift($names, $this->stringValue($parentValues['name'] ?? ''));
            $parent = $parentValues['parent_id'] ?? null;
        }

        return [count($names) - 1, implode(' / ', $names), $parentCode];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{
     *     id: int,
     *     parent_id: int|null,
     *     code: string,
     *     name: string,
     *     type: string,
     *     headcount_limit: int,
     *     cost_center_code: string|null,
     *     cost_center_name: string|null,
     *     approved_headcount: int,
     *     people_count: int,
     *     allocated_fte: float,
     *     vacancy: float,
     *     detail_url: string
     * }>
     */
    private function headcountRows(int $companyId, array $filters): array
    {
        $type = is_string($filters['type'] ?? null) ? $filters['type'] : null;
        $unitQuery = DB::table('organization_units as ou')->leftJoin('cost_centers as cc', 'cc.id', '=', 'ou.cost_center_id')->where('ou.company_id', $companyId);
        if ($type !== null && $type !== '') {
            $unitQuery->where('ou.type', $type);
        }
        $rawUnits = $unitQuery->select(['ou.id', 'ou.parent_id', 'ou.code', 'ou.name', 'ou.type', 'ou.headcount_limit', 'cc.code as cost_center_code', 'cc.name as cost_center_name'])->orderBy('ou.name')->get();
        $metrics = DB::table('job_positions as jp')->leftJoin('employee_assignments as ea', fn (JoinClause $join): JoinClause => $join->on('ea.job_position_id', '=', 'jp.id')->whereDate('ea.effective_from', '<=', today())->where(fn (Builder $query): Builder => $query->whereNull('ea.effective_to')->orWhereDate('ea.effective_to', '>=', today())))->where('jp.company_id', $companyId)->groupBy('jp.organization_unit_id')->selectRaw('jp.organization_unit_id, SUM(jp.approved_headcount) approved, COUNT(DISTINCT ea.employee_id) people_count, COALESCE(SUM(ea.allocation_percent / 100),0) allocated_fte')->get()->keyBy('organization_unit_id');
        $units = [];
        foreach ($rawUnits as $rawUnit) {
            $values = get_object_vars($rawUnit);
            $id = $this->intValue($values['id'] ?? null);
            $metric = $metrics->get($id);
            $metricValues = is_object($metric) ? get_object_vars($metric) : [];
            $approved = $this->intValue($metricValues['approved'] ?? 0);
            $allocated = $this->floatValue($metricValues['allocated_fte'] ?? 0);
            $parentValue = $values['parent_id'] ?? null;
            $units[] = [
                'id' => $id,
                'parent_id' => $parentValue === null ? null : $this->intValue($parentValue),
                'code' => $this->stringValue($values['code'] ?? ''),
                'name' => $this->stringValue($values['name'] ?? ''),
                'type' => $this->stringValue($values['type'] ?? ''),
                'headcount_limit' => $this->intValue($values['headcount_limit'] ?? 0),
                'cost_center_code' => $this->nullableStringValue($values['cost_center_code'] ?? null),
                'cost_center_name' => $this->nullableStringValue($values['cost_center_name'] ?? null),
                'approved_headcount' => $approved,
                'people_count' => $this->intValue($metricValues['people_count'] ?? 0),
                'allocated_fte' => $allocated,
                'vacancy' => $approved - $allocated,
                'detail_url' => route('admin.organization-units.show', $id),
            ];
        }

        return $units;
    }

    /** @return array<string, mixed> */
    private function modelArray(Model $model): array
    {
        $result = [];
        foreach ($model->toArray() as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function floatValue(mixed $value): float
    {
        return is_float($value) || is_int($value) || is_numeric($value) ? (float) $value : 0.0;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableStringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
