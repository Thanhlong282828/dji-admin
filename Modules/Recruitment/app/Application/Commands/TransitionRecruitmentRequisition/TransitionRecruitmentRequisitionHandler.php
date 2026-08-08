<?php

namespace Modules\Recruitment\Application\Commands\TransitionRecruitmentRequisition;

use Modules\Recruitment\Domain\Repositories\RecruitmentWriteRepositoryInterface;

final readonly class TransitionRecruitmentRequisitionHandler
{
    public function __construct(private RecruitmentWriteRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(TransitionRecruitmentRequisitionCommand $c): array
    {
        return $this->repo->transitionRequisition($c->companyId, $c->id, $c->status, $c->actorId, $c->reason);
    }
}
