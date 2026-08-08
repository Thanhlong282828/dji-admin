<?php

namespace Modules\Recruitment\Application\Commands\UploadCandidateResume;

use Modules\Recruitment\Domain\Services\CandidateResumeService;

final readonly class UploadCandidateResumeHandler
{
    public function __construct(private CandidateResumeService $service) {}

    public function handle(UploadCandidateResumeCommand $command): void
    {
        $this->service->upload($command->companyId, $command->candidateId, $command->file, $command->actorId);
    }
}
