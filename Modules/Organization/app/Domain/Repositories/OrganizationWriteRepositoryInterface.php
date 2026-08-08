<?php

namespace Modules\Organization\Domain\Repositories;

interface OrganizationWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCostCenter(int $companyId, array $data): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateCostCenter(int $companyId, int $id, array $data): array;

    public function deleteCostCenter(int $companyId, int $id): void;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createUnit(int $companyId, array $data): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateUnit(int $companyId, int $id, array $data): array;

    /** @return array<string, mixed> */
    public function moveUnit(int $companyId, int $id, ?int $parentId): array;

    /** @return array<string, mixed> */
    public function assignUnitCostCenter(int $companyId, int $id, ?int $costCenterId): array;

    public function deleteUnit(int $companyId, int $id): void;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPosition(int $companyId, array $data): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePosition(int $companyId, int $id, array $data): array;

    public function deletePosition(int $companyId, int $id): void;
}
