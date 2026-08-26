<?php

namespace Modules\Company\Application\Commands\CreateCompany;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\Services\CompanyBranchService;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final readonly class CreateCompanyHandler
{
    public function __construct(private CompanyWriteRepositoryInterface $companies, private CompanyBranchService $branches, private Dispatcher $events) {}

    public function handle(CreateCompanyCommand $command): Company
    {
        $company = DB::transaction(function () use ($command): Company {
            $attributes = $command->attributes;
            $branches = $attributes['branches'] ?? [];
            unset($attributes['branches']);
            $company = $this->companies->create([...$attributes, 'created_by' => $command->actorId, 'updated_by' => $command->actorId]);
            if (is_array($branches) && $branches !== []) {
                $this->branches->apply($company->id, $branches, $command->actorId);
            }

            return $company;
        }, 3);
        $this->events->dispatch(new CompanyCreated($company, $command->actorId));

        return $company;
    }
}
