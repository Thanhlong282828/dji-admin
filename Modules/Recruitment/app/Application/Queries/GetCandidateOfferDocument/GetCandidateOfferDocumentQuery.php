<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferDocument;

final readonly class GetCandidateOfferDocumentQuery
{
    public function __construct(public int $companyId, public int $offerId) {}
}
