<?php

namespace Modules\Company\Application\Queries\GetCompanyDetail;

final readonly class GetCompanyDetailQuery
{
    public function __construct(public int $companyId, public bool $withTrashed = false) {}
}
