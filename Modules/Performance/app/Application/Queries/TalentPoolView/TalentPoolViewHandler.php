<?php

namespace Modules\Performance\Application\Queries\TalentPoolView;

use Modules\Performance\Domain\Repositories\CareerReadRepositoryInterface;

final readonly class TalentPoolViewHandler
{
    public function __construct(private CareerReadRepositoryInterface $repo) {}

    /** @return list<array<string,mixed>> */
    public function handle(TalentPoolViewQuery $q): array
    {
        return $this->repo->talentPool($q->companyId);
    }
}
