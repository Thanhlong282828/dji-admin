<?php

namespace Modules\Company\Application\Commands\CreateCompany;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Models\Company;
use Modules\Company\Domain\Repositories\CompanyWriteRepositoryInterface;

final readonly class CreateCompanyHandler
{
    public function __construct(private CompanyWriteRepositoryInterface $companies, private Dispatcher $events) {}

    public function handle(CreateCompanyCommand $command): Company
    {
        $company = DB::transaction(fn (): Company => $this->companies->create([...$command->attributes, 'created_by' => $command->actorId, 'updated_by' => $command->actorId]));
        $this->events->dispatch(new CompanyCreated($company, $command->actorId));

        return $company;
    }
}
