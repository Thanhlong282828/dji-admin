<?php

namespace Modules\Recruitment\Application\Commands\RemoveCandidateOfferDocument;

use Modules\Recruitment\Domain\Services\CandidateOfferDocumentService;

final readonly class RemoveCandidateOfferDocumentHandler
{
    public function __construct(private CandidateOfferDocumentService $service) {}

    public function handle(RemoveCandidateOfferDocumentCommand $command): void
    {
        $this->service->remove($command->companyId, $command->offerId, $command->actorId);
    }
}
