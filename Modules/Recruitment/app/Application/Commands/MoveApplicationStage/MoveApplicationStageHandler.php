<?php

namespace Modules\Recruitment\Application\Commands\MoveApplicationStage;

use Modules\Recruitment\Domain\Repositories\RecruitmentWriteRepositoryInterface;

final readonly class MoveApplicationStageHandler
{
    public function __construct(private RecruitmentWriteRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(MoveApplicationStageCommand $c): array
    {
        return $this->repo->moveApplication($c->companyId, $c->id, $c->stage, $c->actorId, $c->expectedVersion, $c->reason);
    }
}
