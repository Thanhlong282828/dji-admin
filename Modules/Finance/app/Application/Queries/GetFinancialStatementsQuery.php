<?php

namespace Modules\Finance\Application\Queries;

final readonly class GetFinancialStatementsQuery
{
    public function __construct(public int $companyId, public ?string $dateFrom, public ?string $dateTo, public ?int $accountId, public int $page = 1) {}
}
