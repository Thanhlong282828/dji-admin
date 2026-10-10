<?php

namespace Modules\Tax\Application\Queries;

use App\Models\User;

final readonly class GetTaxReportQuery
{
    public function __construct(
        public int $companyId,
        public ?int $branchId,
        public string $from,
        public string $to,
        public string $currency,
        public ?User $actor = null,
    ) {}
}
