<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferList;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Services\CandidateOfferService;

final readonly class GetCandidateOfferListHandler
{
    public function __construct(private CandidateOfferService $service) {}

    /** @return LengthAwarePaginator<int, CandidateOffer> */
    public function handle(GetCandidateOfferListQuery $query): LengthAwarePaginator
    {
        return $this->service->list($query->companyId);
    }
}
