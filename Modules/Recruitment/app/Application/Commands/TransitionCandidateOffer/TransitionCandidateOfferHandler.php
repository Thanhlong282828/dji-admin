<?php

namespace Modules\Recruitment\Application\Commands\TransitionCandidateOffer;

use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Services\CandidateOfferService;

final readonly class TransitionCandidateOfferHandler
{
    public function __construct(private CandidateOfferService $service) {}

    public function handle(TransitionCandidateOfferCommand $command): CandidateOffer
    {
        return $this->service->transition($command->companyId, $command->offerId, $command->target, $command->actorId, $command->reason, $command->expectedVersion);
    }
}
