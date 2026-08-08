<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferDetail;

final readonly class GetCandidateOfferDetailQuery
{
    public function __construct(public int $companyId, public int $offerId, public bool $canViewSalary) {}
}
