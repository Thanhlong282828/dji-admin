<?php

namespace Modules\Company\Application\Commands\UpdateCompany;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\Services\CompanyBranchService;
use Modules\Company\Application\Services\CompanySellerAccountService;
use Modules\Company\Domain\Events\CompanyUpdated;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final readonly class UpdateCompanyHandler
{
    public function __construct(private CompanyWriteRepositoryInterface $companies, private CompanyBranchService $branches, private CompanySellerAccountService $sellers, private Dispatcher $events) {}

    public function handle(UpdateCompanyCommand $command): Company
    {
        $company = DB::transaction(function () use ($command): Company {
            $attributes = $command->attributes;
            $branches = $attributes['branches'] ?? [];
            $sellers = $attributes['seller_accounts'] ?? [];
            unset($attributes['branches'], $attributes['seller_accounts']);
            $company = $this->companies->update($command->companyId, [...$attributes, 'updated_by' => $command->actorId]);
            if (is_array($branches) && $branches !== []) {
                $this->branches->apply($command->companyId, $branches, $command->actorId);
            }
            if (is_array($sellers) && $sellers !== []) {
                $this->sellers->apply($command->companyId, $sellers);
            }

            return $company;
        }, 3);
        $this->events->dispatch(new CompanyUpdated($company, $command->actorId));

        return $company;
    }
}
