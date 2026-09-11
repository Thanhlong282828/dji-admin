<?php

namespace Modules\POS\Application\Queries;

use Modules\POS\Application\Services\BranchCommerceReadService;

final readonly class GetBranchCommercePageHandler
{
    public function __construct(private BranchCommerceReadService $service) {}

    /** @return array<string, mixed> */
    public function handle(GetBranchCommercePageQuery $query): array
    {
        return $this->service->page($query->page, $query->actor, $query->companyId, $query->filters);
    }
}
