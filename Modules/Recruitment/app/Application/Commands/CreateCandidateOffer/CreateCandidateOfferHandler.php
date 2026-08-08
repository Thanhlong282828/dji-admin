<?php

namespace Modules\Recruitment\Application\Commands\CreateCandidateOffer;

use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Services\CandidateOfferService;

final readonly class CreateCandidateOfferHandler
{
    public function __construct(private CandidateOfferService $service) {}

    public function handle(CreateCandidateOfferCommand $command): CandidateOffer
    {
        return $this->service->create($command->companyId, $command->data, $command->actorId);
    }
}
