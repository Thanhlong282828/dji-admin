<?php

namespace Modules\Organization\Application\Commands\UpdateApprovedHeadcount;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\JobPositionHeadcountChanged;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class UpdateApprovedHeadcountHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(UpdateApprovedHeadcountCommand $c): array
    {
        $r = $this->repo->updatePosition($c->companyId, $c->id, ['approved_headcount' => $c->headcount]);
        $this->events->dispatch(new JobPositionHeadcountChanged($c->companyId, $r, $c->actorId));

        return $r;
    }
}
