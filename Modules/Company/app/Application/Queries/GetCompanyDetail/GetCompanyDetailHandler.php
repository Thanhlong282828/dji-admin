<?php

namespace Modules\Company\Application\Queries\GetCompanyDetail;

use Modules\Company\Application\DTOs\CompanyViewModel;
use Modules\Company\Domain\Repositories\CompanyReadRepositoryInterface;

final readonly class GetCompanyDetailHandler
{
    public function __construct(private CompanyReadRepositoryInterface $companies) {}

    public function handle(GetCompanyDetailQuery $query): CompanyViewModel
    {
        return CompanyViewModel::fromModel($this->companies->findOrFail($query->companyId, $query->withTrashed));
    }
}
