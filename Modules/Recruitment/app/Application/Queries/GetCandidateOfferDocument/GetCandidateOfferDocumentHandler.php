<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferDocument;

use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Services\CandidateOfferDocumentService;

final readonly class GetCandidateOfferDocumentHandler
{
    public function __construct(private CandidateOfferDocumentService $service) {}

    public function handle(GetCandidateOfferDocumentQuery $query): CandidateOffer
    {
        return $this->service->downloadable($query->companyId, $query->offerId);
    }
}
