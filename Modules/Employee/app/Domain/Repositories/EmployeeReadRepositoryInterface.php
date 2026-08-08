<?php

namespace Modules\Employee\Domain\Repositories;

use Generator;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmployeeReadRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $companyId, array $filters): LengthAwarePaginator;

    /** @return array<string,mixed> */
    public function detail(int $companyId, int $id, bool $sensitive = false): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, list<bool|float|int|string|null>, mixed, void>
     */
    public function exportRows(int $companyId, array $filters): Generator;
}
