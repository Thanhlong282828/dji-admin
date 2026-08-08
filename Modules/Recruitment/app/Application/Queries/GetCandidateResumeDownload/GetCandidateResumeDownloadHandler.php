<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateResumeDownload;

use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Services\CandidateResumeService;

final readonly class GetCandidateResumeDownloadHandler
{
    public function __construct(private CandidateResumeService $service) {}

    public function handle(GetCandidateResumeDownloadQuery $query): Candidate
    {
        return $this->service->downloadable($query->companyId, $query->candidateId);
    }
}
