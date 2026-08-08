<?php

namespace Modules\Recruitment\Application\Commands\CreateApplication;

use Modules\Recruitment\Domain\Repositories\RecruitmentWriteRepositoryInterface;

final readonly class CreateApplicationHandler
{
    public function __construct(private RecruitmentWriteRepositoryInterface $repo) {}

    /** @return array<string, mixed> */
    public function handle(CreateApplicationCommand $c): array
    {
        return $this->repo->createApplication($c->companyId, $c->data, $c->actorId);
    }
}
