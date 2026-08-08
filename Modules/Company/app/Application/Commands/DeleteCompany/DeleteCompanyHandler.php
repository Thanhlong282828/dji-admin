<?php

namespace Modules\Company\Application\Commands\DeleteCompany;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Events\CompanyDeleted;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final readonly class DeleteCompanyHandler
{
    public function __construct(private CompanyWriteRepositoryInterface $companies, private Dispatcher $events) {}

    public function handle(DeleteCompanyCommand $command): Company
    {
        $company = DB::transaction(fn (): Company => $this->companies->delete($command->companyId, $command->actorId));
        $this->events->dispatch(new CompanyDeleted($company, $command->actorId));

        return $company;
    }
}
