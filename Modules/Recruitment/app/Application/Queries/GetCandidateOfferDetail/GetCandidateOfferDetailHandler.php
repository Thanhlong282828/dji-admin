<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferDetail;

use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Services\CandidateOfferService;

final readonly class GetCandidateOfferDetailHandler
{
    public function __construct(private CandidateOfferService $service) {}

    public function handle(GetCandidateOfferDetailQuery $query): CandidateOffer
    {
        $offer = $this->service->detail($query->companyId, $query->offerId);
        if (! $query->canViewSalary) {
            $offer->setAttribute('base_salary', null);
        }

        return $offer;
    }
}
