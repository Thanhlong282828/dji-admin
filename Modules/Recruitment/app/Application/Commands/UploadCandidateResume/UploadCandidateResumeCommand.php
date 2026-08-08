<?php

namespace Modules\Recruitment\Application\Commands\UploadCandidateResume;

use Illuminate\Http\UploadedFile;

final readonly class UploadCandidateResumeCommand
{
    public function __construct(public int $companyId, public int $candidateId, public UploadedFile $file, public int $actorId) {}
}
