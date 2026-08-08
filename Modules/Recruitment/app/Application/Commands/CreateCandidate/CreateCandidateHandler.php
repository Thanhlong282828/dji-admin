<?php

namespace Modules\Recruitment\Application\Commands\CreateCandidate;

use Modules\Recruitment\Domain\Repositories\RecruitmentWriteRepositoryInterface;

final readonly class CreateCandidateHandler
{
    public function __construct(private RecruitmentWriteRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(CreateCandidateCommand $c): array
    {
        return $this->repo->createCandidate($c->companyId, $c->data);
    }
}
