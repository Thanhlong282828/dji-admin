<?php

namespace Modules\Company\Domain\Repositories;

use Modules\Company\Domain\Enums\CompanyStatus;
use Modules\Company\Domain\Models\Company;

interface CompanyWriteRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Company;

    /** @param array<string, mixed> $attributes */
    public function update(int $id, array $attributes): Company;

    public function changeStatus(int $id, CompanyStatus $status, int $actorId): Company;

    public function delete(int $id, int $actorId): Company;

    public function restore(int $id, int $actorId): Company;
}
