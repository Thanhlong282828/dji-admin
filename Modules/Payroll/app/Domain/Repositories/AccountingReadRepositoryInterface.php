<?php

namespace Modules\Payroll\Domain\Repositories;

use Modules\Payroll\Application\DTOs\AccountingViewModel;

interface AccountingReadRepositoryInterface
{
    /** @param array{search:string,status:string,page:int} $filters */
    public function accounts(int $companyId, array $filters): AccountingViewModel;

    public function account(int $companyId, ?int $accountId): AccountingViewModel;

    /** @param array{search:string,status:string,page:int} $filters */
    public function policies(int $companyId, array $filters): AccountingViewModel;

    public function policy(int $companyId, ?int $policyId): AccountingViewModel;

    /** @param array{search:string,status:string,page:int} $filters */
    public function mappings(int $companyId, array $filters): AccountingViewModel;

    public function mapping(int $companyId, ?int $mappingId): AccountingViewModel;

    /** @param array{search:string,status:string,page:int} $filters */
    public function journals(int $companyId, array $filters): AccountingViewModel;

    public function journal(int $companyId, int $journalId): AccountingViewModel;
}
