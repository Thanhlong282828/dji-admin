<?php

namespace Modules\Company\Application\Commands\ChangeCompanyStatus;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Events\CompanyStatusChanged;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final readonly class ChangeCompanyStatusHandler
{
    public function __construct(private CompanyWriteRepositoryInterface $companies, private Dispatcher $events) {}

    public function handle(ChangeCompanyStatusCommand $command): Company
    {
        $company = DB::transaction(fn (): Company => $this->companies->changeStatus($command->companyId, $command->status, $command->actorId));
        $this->events->dispatch(new CompanyStatusChanged($company, $command->actorId));

        return $company;
    }
}
