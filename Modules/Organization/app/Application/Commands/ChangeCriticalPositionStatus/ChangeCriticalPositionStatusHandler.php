<?php

namespace Modules\Organization\Application\Commands\ChangeCriticalPositionStatus;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\JobPositionCriticalStatusChanged;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class ChangeCriticalPositionStatusHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(ChangeCriticalPositionStatusCommand $c): array
    {
        $r = $this->repo->updatePosition($c->companyId, $c->id, ['is_critical' => $c->critical]);
        $this->events->dispatch(new JobPositionCriticalStatusChanged($c->companyId, $r, $c->actorId));

        return $r;
    }
}
