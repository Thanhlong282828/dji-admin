<?php

namespace Modules\Recruitment\Application\Commands\UploadCandidateOfferDocument;

use Illuminate\Http\UploadedFile;

final readonly class UploadCandidateOfferDocumentCommand
{
    public function __construct(public int $companyId, public int $offerId, public UploadedFile $file, public int $actorId) {}
}
