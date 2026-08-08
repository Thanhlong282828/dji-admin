<?php

namespace Modules\Employee\Domain\Repositories;

interface EmployeeWriteRepositoryInterface
{
    /** @param array<string, mixed> $data
     * @return array<string,mixed>
     */
    public function create(int $companyId, array $data): array;

    /** @param array<string, mixed> $data
     * @return array<string,mixed>
     */
    public function update(int $companyId, int $id, array $data): array;

    public function delete(int $companyId, int $id): void;

    /** @return array<string,mixed> */
    public function restore(int $companyId, int $id): array;
}
