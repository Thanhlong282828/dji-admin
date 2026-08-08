<?php

namespace Modules\Company\Application\Commands\UpdateCompany;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Events\CompanyUpdated;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final readonly class UpdateCompanyHandler
{
    public function __construct(private CompanyWriteRepositoryInterface $companies, private Dispatcher $events) {}

    public function handle(UpdateCompanyCommand $command): Company
    {
        $company = DB::transaction(fn (): Company => $this->companies->update($command->companyId, [...$command->attributes, 'updated_by' => $command->actorId]));
        $this->events->dispatch(new CompanyUpdated($company, $command->actorId));

        return $company;
    }
}
