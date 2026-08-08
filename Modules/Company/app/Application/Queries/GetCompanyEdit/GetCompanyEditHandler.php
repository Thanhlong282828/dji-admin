<?php

namespace Modules\Company\Application\Queries\GetCompanyEdit;

use Modules\Company\Application\DTOs\CompanyViewModel;
use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;

final readonly class GetCompanyEditHandler
{
    public function __construct(private CompanyReadRepositoryInterface $companies) {}

    public function handle(GetCompanyEditQuery $query): CompanyViewModel
    {
        return CompanyViewModel::fromModel($this->companies->findOrFail($query->companyId));
    }
}
