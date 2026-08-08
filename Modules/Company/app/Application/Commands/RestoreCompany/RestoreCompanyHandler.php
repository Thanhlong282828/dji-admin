<?php

namespace Modules\Company\Application\Commands\RestoreCompany;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Events\CompanyRestored;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final readonly class RestoreCompanyHandler
{
    public function __construct(private CompanyWriteRepositoryInterface $companies, private Dispatcher $events) {}

    public function handle(RestoreCompanyCommand $command): Company
    {
        $company = DB::transaction(fn (): Company => $this->companies->restore($command->companyId, $command->actorId));
        $this->events->dispatch(new CompanyRestored($company, $command->actorId));

        return $company;
    }
}
