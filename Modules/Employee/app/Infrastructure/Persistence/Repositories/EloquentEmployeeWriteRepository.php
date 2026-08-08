<?php

namespace Modules\Employee\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Repositories\EmployeeWriteRepositoryInterface;
use Modules\Employee\Domain\Services\EmployeeIdentityService;

final readonly class EloquentEmployeeWriteRepository implements EmployeeWriteRepositoryInterface
{
    public function __construct(private EmployeeIdentityService $identity) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(int $companyId, array $data): array
    {
        return DB::transaction(function () use ($companyId, $data): array {
            $this->prepareIdentity($data);
            $this->validateManager($companyId, null, $data['manager_id'] ?? null);

            return $this->normalizeArray(Employee::query()->create([...$data, 'company_id' => $companyId])->toArray());
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $companyId, int $id, array $data): array
    {
        return DB::transaction(function () use ($companyId, $id, $data): array {
            $employee = Employee::query()->where('company_id', $companyId)->findOrFail($id);
            $this->prepareIdentity($data);
            $this->validateManager($companyId, $id, $data['manager_id'] ?? $employee->manager_id);
            $employee->update($data);

            return $this->normalizeArray($employee->refresh()->toArray());
        });
    }

    public function delete(int $companyId, int $id): void
    {
        DB::transaction(fn () => Employee::query()->where('company_id', $companyId)->findOrFail($id)->delete());
    }

    public function restore(int $companyId, int $id): array
    {
        return DB::transaction(function () use ($companyId, $id): array {
            $employee = Employee::withTrashed()->where('company_id', $companyId)->findOrFail($id);
            $employee->restore();

            $restored = $employee->fresh();

            return $restored === null ? [] : $this->normalizeArray($restored->toArray());
        });
    }

    /** @param array<string, mixed> $data */
    private function prepareIdentity(array &$data): void
    {
        if (! array_key_exists('identity_number', $data)) {
            return;
        }
        $identity = $data['identity_number'];
        $data['identity_number_hash'] = is_string($identity) && $identity !== '' ? $this->identity->hash($identity) : null;
    }

    private function validateManager(int $companyId, ?int $employeeId, mixed $managerId): void
    {
        if ($managerId === null || $managerId === '') {
            return;
        }
        $normalizedManagerId = is_int($managerId) || is_numeric($managerId) ? (int) $managerId : 0;
        $manager = Employee::query()->where('company_id', $companyId)->findOrFail($normalizedManagerId);
        if ($employeeId !== null && $manager->id === $employeeId) {
            throw ValidationException::withMessages(['manager_id' => 'Nhân viên không thể là quản lý của chính mình.']);
        }
        $cursor = $manager->manager_id;
        while ($cursor !== null) {
            if ($cursor === $employeeId) {
                throw ValidationException::withMessages(['manager_id' => 'Quan hệ quản lý tạo vòng lặp.']);
            } $cursor = Employee::query()->where('company_id', $companyId)->whereKey($cursor)->value('manager_id');
        }
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
}
