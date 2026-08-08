<?php

namespace Modules\Organization\Domain\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Application\DTOs\OrganizationExportData;
use Modules\Organization\Domain\Enums\OrganizationExportType;

interface OrganizationReadRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function costCenters(int $companyId, array $filters): LengthAwarePaginator;

    /** @return array<string,mixed> */
    public function costCenter(int $companyId, int $id): array;

    /** @return array<string,mixed> */
    public function costCenterBudget(int $companyId): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function units(int $companyId, array $filters): LengthAwarePaginator;

    /** @return array<string,mixed> */
    public function unit(int $companyId, int $id): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function tree(int $companyId, array $filters = []): array;

    /** @return list<array<string,mixed>> */
    public function breadcrumb(int $companyId, int $id): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function positions(int $companyId, array $filters): LengthAwarePaginator;

    /** @return array<string, mixed> */
    public function position(int $companyId, int $id): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(int $companyId, array $filters): array;

    /** @param array<string, mixed> $filters */
    public function export(int $companyId, OrganizationExportType $type, array $filters): OrganizationExportData;
}
