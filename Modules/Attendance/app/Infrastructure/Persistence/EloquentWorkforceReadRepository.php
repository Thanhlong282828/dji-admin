<?php

namespace Modules\Attendance\Infrastructure\Persistence;

use App\Services\WorkforceCacheRegistry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Attendance\Application\DTOs\WorkforceManagementViewModel;
use Modules\Attendance\Domain\Repositories\WorkforceReadRepositoryInterface;

final readonly class EloquentWorkforceReadRepository implements WorkforceReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $database, private WorkforceCacheRegistry $cache) {}

    public function management(int $companyId, string $section, ?string $search): WorkforceManagementViewModel
    {
        $filterHash = hash('sha256', $section."\0".($search ?? ''));
        $readModel = 'management:'.$filterHash;
        $cached = $this->cache->rememberExternal($companyId, 'workforce', $readModel, 300, fn (): string => $this->managementUncached($companyId, $section, $search)->toJson());
        if (is_string($cached)) {
            return WorkforceManagementViewModel::fromJson($cached);
        }

        $this->cache->invalidate($companyId, 'workforce');
        $fresh = $this->managementUncached($companyId, $section, $search);
        $this->cache->rememberExternal($companyId, 'workforce', $readModel, 300, fn (): string => $fresh->toJson());

        return $fresh;
    }

    private function managementUncached(int $companyId, string $section, ?string $search): WorkforceManagementViewModel
    {
        $table = match ($section) {
            'calendars' => 'work_calendars',
            'holidays' => 'company_holidays',
            'shifts' => 'work_shifts',
            'assignments' => 'employee_shift_assignments',
            default => 'work_calendars',
        };
        $query = $this->database->table($table)->where($table.'.company_id', $companyId);
        if ($table === 'employee_shift_assignments') {
            $query->leftJoin('employees', 'employees.id', '=', $table.'.employee_id')->leftJoin('work_shifts', 'work_shifts.id', '=', $table.'.work_shift_id')->select($table.'.*', 'employees.employee_code', 'employees.full_name', 'work_shifts.name as shift_name');
        }
        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $nested) use ($search, $table): void {
                if ($table === 'company_holidays') {
                    $nested->where('name', 'like', '%'.$search.'%');
                } elseif ($table === 'employee_shift_assignments') {
                    $nested->where('employees.employee_code', 'like', '%'.$search.'%')->orWhere('work_shifts.name', 'like', '%'.$search.'%');
                } else {
                    $nested->where($table.'.code', 'like', '%'.$search.'%')->orWhere($table.'.name', 'like', '%'.$search.'%');
                }
            });
        }
        /** @var list<array<string, bool|int|string|null>> $rows */
        $rows = $query->orderByDesc($table.'.id')->limit(200)->get()->map(static function (object $row): array {
            /** @var array<string, bool|int|string|null> $data */
            $data = (array) $row;

            return $data;
        })->all();
        /** @var list<array{id:int, code:string, name:string}> $calendars */
        $calendars = $this->database->table('work_calendars')->where('company_id', $companyId)->orderBy('name')->get(['id', 'code', 'name'])->map(fn (object $row): array => ['id' => $this->integer($row, 'id'), 'code' => $this->text($row, 'code'), 'name' => $this->text($row, 'name')])->all();
        /** @var list<array{id:int, code:string, name:string}> $shifts */
        $shifts = $this->database->table('work_shifts')->where('company_id', $companyId)->orderBy('name')->get(['id', 'code', 'name'])->map(fn (object $row): array => ['id' => $this->integer($row, 'id'), 'code' => $this->text($row, 'code'), 'name' => $this->text($row, 'name')])->all();
        /** @var list<array{id:int, employee_code:string, name:string}> $employees */
        $employees = $this->database->table('employees')->where('company_id', $companyId)->orderBy('employee_code')->limit(500)->get(['id', 'employee_code', 'full_name'])->map(fn (object $row): array => ['id' => $this->integer($row, 'id'), 'employee_code' => $this->text($row, 'employee_code'), 'name' => $this->text($row, 'full_name')])->all();

        return new WorkforceManagementViewModel($section, $rows, $calendars, $shifts, $employees);
    }

    private function integer(object $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
    }

    private function text(object $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }
}
