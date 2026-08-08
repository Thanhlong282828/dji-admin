<?php

namespace Modules\Organization\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Organization\Domain\Models\CostCenter;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;
use Modules\Organization\Domain\Services\OrganizationCycleDetector;
use Modules\Organization\Domain\Services\OrganizationHierarchyValidator;

final readonly class EloquentOrganizationWriteRepository implements OrganizationWriteRepositoryInterface
{
    public function __construct(private OrganizationCycleDetector $cycles, private OrganizationHierarchyValidator $hierarchy) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCostCenter(int $companyId, array $data): array
    {
        return DB::transaction(fn (): array => $this->modelArray(CostCenter::query()->create([...$data, 'company_id' => $companyId])));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateCostCenter(int $companyId, int $id, array $data): array
    {
        return DB::transaction(function () use ($companyId, $id, $data) {
            $m = CostCenter::query()->where('company_id', $companyId)->findOrFail($id);
            $m->update($data);

            return $this->modelArray($m->refresh());
        });
    }

    public function deleteCostCenter(int $companyId, int $id): void
    {
        DB::transaction(function () use ($companyId, $id) {
            $m = CostCenter::query()->where('company_id', $companyId)->findOrFail($id);
            if ($m->organizationUnits()->exists()) {
                throw ValidationException::withMessages(['cost_center' => 'Không thể xóa trung tâm chi phí đang được đơn vị tổ chức sử dụng.']);
            }$m->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createUnit(int $companyId, array $data): array
    {
        return DB::transaction(function () use ($companyId, $data) {
            $this->validateUnitRelations($companyId, $data);

            return $this->modelArray(OrganizationUnit::query()->create([...$data, 'company_id' => $companyId]));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateUnit(int $companyId, int $id, array $data): array
    {
        return DB::transaction(function () use ($companyId, $id, $data) {
            $this->validateUnitRelations($companyId, $data, $id);
            $m = OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($id);
            $m->update($data);

            return $this->modelArray($m->refresh());
        });
    }

    /** @return array<string, mixed> */
    public function moveUnit(int $companyId, int $id, ?int $parentId): array
    {
        return DB::transaction(function () use ($companyId, $id, $parentId) {
            $m = OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($id);
            if ($this->cycles->wouldCreateCycle($id, $parentId)) {
                throw ValidationException::withMessages(['parent_id' => 'Không thể chuyển đơn vị vào chính nó hoặc một đơn vị con.']);
            }if ($parentId !== null) {
                $parent = OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($parentId);
                $this->hierarchy->validate($parent->type, $m->type);
            }$m->update(['parent_id' => $parentId]);

            return $this->modelArray($m->refresh());
        });
    }

    /** @return array<string, mixed> */
    public function assignUnitCostCenter(int $companyId, int $id, ?int $costCenterId): array
    {
        return DB::transaction(function () use ($companyId, $id, $costCenterId): array {
            if ($costCenterId !== null) {
                CostCenter::query()->where('company_id', $companyId)->findOrFail($costCenterId);
            }
            $unit = OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($id);
            $unit->update(['cost_center_id' => $costCenterId]);

            return $this->modelArray($unit->refresh());
        });
    }

    public function deleteUnit(int $companyId, int $id): void
    {
        DB::transaction(function () use ($companyId, $id) {
            $m = OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($id);
            if ($m->children()->exists()) {
                throw ValidationException::withMessages(['organization_unit' => 'Không thể xóa đơn vị còn đơn vị con.']);
            }if ($m->positions()->exists() || DB::table('employee_assignments')->where('organization_unit_id', $id)->exists()) {
                throw ValidationException::withMessages(['organization_unit' => 'Không thể xóa đơn vị đang có vị trí hoặc phân công nhân sự.']);
            }$m->delete();
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createPosition(int $companyId, array $data): array
    {
        return DB::transaction(function () use ($companyId, $data) {
            $this->validatePositionUnit($companyId, $this->requiredInt($data, 'organization_unit_id'));

            return $this->modelArray(JobPosition::query()->create([...$data, 'company_id' => $companyId]));
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function updatePosition(int $companyId, int $id, array $data): array
    {
        return DB::transaction(function () use ($companyId, $id, $data) {
            $this->validatePositionUnit($companyId, $this->requiredInt($data, 'organization_unit_id'));
            $m = JobPosition::query()->where('company_id', $companyId)->findOrFail($id);
            $m->update($data);

            return $this->modelArray($m->refresh());
        });
    }

    public function deletePosition(int $companyId, int $id): void
    {
        DB::transaction(function () use ($companyId, $id) {
            $m = JobPosition::query()->where('company_id', $companyId)->findOrFail($id);
            if ($m->assignments()->exists()) {
                throw ValidationException::withMessages(['job_position' => 'Không thể xóa vị trí đang có phân công nhân sự.']);
            }$m->delete();
        });
    }

    /** @param array<string, mixed> $data */
    private function validateUnitRelations(int $companyId, array $data, ?int $unitId = null): void
    {
        $parentId = isset($data['parent_id']) ? $this->requiredInt($data, 'parent_id') : null;
        if ($unitId !== null && $this->cycles->wouldCreateCycle($unitId, $parentId)) {
            throw ValidationException::withMessages(['parent_id' => 'Cấu trúc cây tạo vòng lặp.']);
        }$parentType = null;
        if ($parentId !== null) {
            $parent = OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($parentId);
            $parentType = $parent->type;
        }if (isset($data['cost_center_id'])) {
            CostCenter::query()->where('company_id', $companyId)->findOrFail($this->requiredInt($data, 'cost_center_id'));
        }
        $type = $data['type'] ?? null;
        if (! is_string($type)) {
            throw ValidationException::withMessages(['type' => 'Loại đơn vị không hợp lệ.']);
        }
        $this->hierarchy->validate($parentType, $type);
    }

    private function validatePositionUnit(int $companyId, int $unitId): void
    {
        OrganizationUnit::query()->where('company_id', $companyId)->findOrFail($unitId);
    }

    /** @param array<string, mixed> $data */
    private function requiredInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw ValidationException::withMessages([$key => 'Giá trị phải là số nguyên hợp lệ.']);
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
}
