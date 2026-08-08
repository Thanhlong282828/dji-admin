<?php

namespace Modules\Company\Infrastructure\Persistence\Repositories;

use Modules\Company\Domain\Enums\CompanyStatus;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final class EloquentCompanyWriteRepository implements CompanyWriteRepositoryInterface
{
    public function create(array $attributes): Company
    {
        return Company::query()->create($this->legacyColumns($attributes));
    }

    public function update(int $id, array $attributes): Company
    {
        $company = Company::query()->findOrFail($id);
        $company->update($this->legacyColumns($attributes));

        return $company->refresh();
    }

    public function changeStatus(int $id, CompanyStatus $status, int $actorId): Company
    {
        return $this->update($id, ['status' => $status, 'is_active' => $status === CompanyStatus::ACTIVE, 'updated_by' => $actorId]);
    }

    public function delete(int $id, int $actorId): Company
    {
        $company = Company::query()->findOrFail($id);
        $company->update(['updated_by' => $actorId]);
        $company->delete();

        return $company;
    }

    public function restore(int $id, int $actorId): Company
    {
        $company = Company::withTrashed()->findOrFail($id);
        $company->restore();
        $company->update(['updated_by' => $actorId]);

        return $company->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function legacyColumns(array $attributes): array
    {
        if (isset($attributes['currency_code'])) {
            $attributes['currency'] = $attributes['currency_code'];
        }
        if (isset($attributes['status'])) {
            $status = $attributes['status'] instanceof CompanyStatus ? $attributes['status']->value : $attributes['status'];
            $attributes['is_active'] = $status === CompanyStatus::ACTIVE->value;
        }

        return $attributes;
    }
}
