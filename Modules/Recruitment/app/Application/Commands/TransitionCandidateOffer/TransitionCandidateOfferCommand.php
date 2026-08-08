<?php

namespace Modules\Recruitment\Application\Commands\TransitionCandidateOffer;

use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;

final readonly class TransitionCandidateOfferCommand
{
    public function __construct(
        public int $companyId,
        public int $offerId,
        public CandidateOfferStatus $target,
        public int $actorId,
        public ?string $reason,
        public ?int $expectedVersion,
    ) {}
}
