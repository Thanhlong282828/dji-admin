<?php

namespace Modules\Finance\Application\Queries;

use App\Models\User;

final readonly class GetFinancialStatementsQuery
{
    public function __construct(
        public int $companyId,
        public ?string $dateFrom,
        public ?string $dateTo,
        public ?int $accountId,
        public int $page = 1,
        public ?int $branchId = null,
        public ?string $currency = null,
        public ?User $actor = null,
    ) {}
}
