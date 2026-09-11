<?php

namespace Modules\Commerce\Application\Queries;

use App\Models\User;

final readonly class GetUnifiedSalesHistoryQuery
{
    public function __construct(
        public User $actor,
        public int $companyId,
        public string $source = '',
        public string $search = '',
        public string $dateFrom = '',
        public string $dateTo = '',
        public int $branchId = 0,
    ) {}
}
