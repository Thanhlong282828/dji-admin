<?php

namespace Modules\Employee\Infrastructure\Persistence\Repositories;

use BackedEnum;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Repositories\EmployeeReadRepositoryInterface;
use Modules\Employee\Domain\Services\EmployeeIdentityService;

final readonly class EloquentEmployeeReadRepository implements EmployeeReadRepositoryInterface
{
    public function __construct(private EmployeeIdentityService $identity) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $companyId, array $filters): LengthAwarePaginator
    {
        $requestedSort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'created_at';
        $sort = in_array($requestedSort, ['employee_code', 'full_name', 'join_date', 'official_date', 'employment_status', 'created_at'], true) ? $requestedSort : 'created_at';
        $direction = ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';

        $query = Employee::query()
            ->select(['id', 'uuid', 'company_id', 'user_id', 'manager_id', 'employee_code', 'full_name', 'date_of_birth', 'gender', 'avatar_storage_key', 'avatar_url', 'personal_email', 'work_email', 'join_date', 'official_date', 'employment_status', 'employment_type', 'created_at'])
            ->with(['manager:id,employee_code,full_name', 'user:id,email,access_enabled', 'user.roles:id,name'])
            ->with(['assignments' => function (Relation $relation): void {
                if (! $relation instanceof HasMany) {
                    return;
                }
                $assignments = $relation;
                $assignments->with(['organizationUnit:id,code,name', 'jobPosition:id,code,title,career_level'])
                    ->where('assignment_type', 'primary')
                    ->whereDate('effective_from', '<=', today())
                    ->where(function (Builder $active): void {
                        $active->whereNull('effective_to')->orWhereDate('effective_to', '>=', today());
                    });
            }])
            ->where('company_id', $companyId);

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $employeeQuery) use ($search): void {
                $term = '%'.$search.'%';
                $employeeQuery->where('employee_code', 'like', $term)
                    ->orWhere('full_name', 'like', $term)
                    ->orWhere('personal_email', 'like', $term)
                    ->orWhere('work_email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }
        foreach (['employment_status', 'employment_type'] as $filter) {
            $value = $filters[$filter] ?? null;
            if (is_string($value) && $value !== '') {
                $query->where($filter, $value);
            }
        }
        if (array_key_exists('has_user', $filters)) {
            filter_var($filters['has_user'], FILTER_VALIDATE_BOOL) ? $query->whereNotNull('user_id') : $query->whereNull('user_id');
        }
        $perPageValue = $filters['per_page'] ?? 15;
        $perPage = is_int($perPageValue) || is_numeric($perPageValue) ? (int) $perPageValue : 15;

        return $query->orderBy($sort, $direction)->paginate(min(100, max(5, $perPage)))
            ->through(fn (Employee $employee) => $this->map($employee));
    }

    public function detail(int $companyId, int $id, bool $sensitive = false): array
    {
        $employee = Employee::query()->with(['company:id,code,name', 'user.roles:id,name', 'manager:id,employee_code,full_name', 'directReports:id,manager_id,employee_code,full_name', 'assignments.organizationUnit', 'assignments.jobPosition', 'histories', 'credentials', 'contracts', 'consents', 'documents'])->where('company_id', $companyId)->findOrFail($id);
        $data = $this->normalizeArray($employee->toArray());
        $data['identity_number'] = $sensitive ? $employee->identity_number : $this->identity->mask($employee->identity_number);

        return $data;
    }

    /** @return array<string,mixed> */
    private function map(Employee $employee): array
    {
        $data = $this->normalizeArray($employee->toArray());
        $data['has_avatar'] = is_string($employee->avatar_storage_key) && $employee->avatar_storage_key !== '';
        $data['avatar_url'] = is_string($employee->avatar_url) && $employee->avatar_url !== '' ? $employee->avatar_url : null;
        unset($data['avatar_storage_key']);
        unset($data['identity_number'],$data['identity_number_hash']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function normalizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<bool|float|int|string|null>, mixed, void>
     */
    public function exportRows(int $companyId, array $filters): Generator
    {
        $query = Employee::query()
            ->with([
                'manager:id,employee_code,full_name',
                'assignments' => function (Relation $relation): void {
                    $relation->where('assignment_type', 'primary')
                        ->whereDate('effective_from', '<=', today())
                        ->where(fn (Builder $active) => $active->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
                        ->with(['organizationUnit:id,name', 'jobPosition:id,title']);
                },
                'contracts' => fn (Relation $relation) => $relation->latest('starts_at')->limit(1),
                'credentials' => fn (Relation $relation) => $relation->whereNotNull('expires_at')->oldest('expires_at')->limit(1),
            ])
            ->where('company_id', $companyId);
        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where(fn (Builder $builder) => $builder->where('employee_code', 'like', '%'.$search.'%')->orWhere('full_name', 'like', '%'.$search.'%'));
        }
        $status = $filters['employment_status'] ?? null;
        if (is_string($status) && $status !== '') {
            $query->where('employment_status', $status);
        }
        // cursor() cannot batch eager-loaded relations and causes one relationship
        // query per employee. lazyById() keeps memory bounded while eager-loading
        // each 500-row window in a stable order.
        foreach ($query->lazyById(500) as $employee) {
            $assignment = $employee->assignments->first();
            $contract = $employee->contracts->first();
            $credential = $employee->credentials->first();
            $expiresAt = $credential !== null && is_string($credential->expires_at)
                ? CarbonImmutable::parse($credential->expires_at)
                : null;
            $expiry = match (true) {
                $expiresAt === null => 'N/A',
                $expiresAt->isPast() => 'Expired',
                $expiresAt->diffInDays(today()) <= 30 => 'Expiring',
                default => 'Valid',
            };
            $unit = $assignment?->organizationUnit;
            $position = $assignment?->jobPosition;
            $unitName = $unit === null ? '' : (string) $unit->name;
            $positionTitle = $position === null ? '' : (string) $position->title;
            $managerName = $employee->manager === null ? '' : (string) $employee->manager->full_name;
            $allocation = $assignment === null ? '0.00' : (string) $assignment->allocation_percent;
            $joinDate = $employee->join_date;
            $contractType = $contract === null ? '' : $this->enumString($contract->type);
            $contractStatus = $contract === null ? '' : $this->enumString($contract->status);
            yield [
                (string) $employee->employee_code,
                (string) $employee->full_name,
                is_string($employee->work_email) ? $employee->work_email : '',
                $unitName,
                $positionTitle,
                $managerName,
                $this->enumString($employee->employment_status),
                $assignment !== null,
                $allocation,
                $joinDate,
                $contractType,
                $contractStatus,
                $expiry,
                $employee->user_id !== null,
            ];
        }
    }

    private function enumString(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            is_string($value) => $value,
            default => '',
        };
    }
}
