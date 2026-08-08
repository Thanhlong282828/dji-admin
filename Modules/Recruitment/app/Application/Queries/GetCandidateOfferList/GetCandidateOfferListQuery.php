<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferList;

final readonly class GetCandidateOfferListQuery
{
    public function __construct(public int $companyId) {}
}
