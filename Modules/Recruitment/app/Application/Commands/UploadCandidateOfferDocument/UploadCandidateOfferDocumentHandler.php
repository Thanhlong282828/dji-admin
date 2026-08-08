<?php

namespace Modules\Recruitment\Application\Commands\UploadCandidateOfferDocument;

use Modules\Recruitment\Domain\Services\CandidateOfferDocumentService;

final readonly class UploadCandidateOfferDocumentHandler
{
    public function __construct(private CandidateOfferDocumentService $service) {}

    public function handle(UploadCandidateOfferDocumentCommand $command): void
    {
        $this->service->upload($command->companyId, $command->offerId, $command->file, $command->actorId);
    }
}
