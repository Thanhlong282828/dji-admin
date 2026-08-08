<?php

namespace Modules\Recruitment\Application\Commands\CreateCandidateOffer;

use Modules\Recruitment\Application\DTOs\CandidateOfferData;

final readonly class CreateCandidateOfferCommand
{
    public function __construct(public int $companyId, public CandidateOfferData $data, public int $actorId) {}
}
