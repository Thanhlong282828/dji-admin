<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateResumeDownload;

final readonly class GetCandidateResumeDownloadQuery
{
    public function __construct(public int $companyId, public int $candidateId) {}
}
