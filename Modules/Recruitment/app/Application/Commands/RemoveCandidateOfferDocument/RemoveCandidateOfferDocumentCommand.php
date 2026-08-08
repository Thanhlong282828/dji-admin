<?php

namespace Modules\Recruitment\Application\Commands\RemoveCandidateOfferDocument;

final readonly class RemoveCandidateOfferDocumentCommand
{
    public function __construct(public int $companyId, public int $offerId, public int $actorId) {}
}
