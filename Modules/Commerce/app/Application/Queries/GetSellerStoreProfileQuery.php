<?php

namespace Modules\Commerce\Application\Queries;

final readonly class GetSellerStoreProfileQuery
{
    public function __construct(public int $companyId, public int $userId) {}
}
