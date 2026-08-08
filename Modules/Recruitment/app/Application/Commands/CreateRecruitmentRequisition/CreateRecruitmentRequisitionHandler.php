<?php

namespace Modules\Recruitment\Application\Commands\CreateRecruitmentRequisition;

use Modules\Recruitment\Domain\Repositories\RecruitmentWriteRepositoryInterface;

final readonly class CreateRecruitmentRequisitionHandler
{
    public function __construct(private RecruitmentWriteRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(CreateRecruitmentRequisitionCommand $c): array
    {
        return $this->repo->createRequisition($c->companyId, $c->data);
    }
}
