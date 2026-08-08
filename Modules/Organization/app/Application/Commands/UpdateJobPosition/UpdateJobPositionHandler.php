<?php

namespace Modules\Organization\Application\Commands\UpdateJobPosition;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\JobPositionUpdated;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class UpdateJobPositionHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(UpdateJobPositionCommand $c): array
    {
        $r = $this->repo->updatePosition($c->companyId, $c->id, $c->data);
        $this->events->dispatch(new JobPositionUpdated($c->companyId, $r, $c->actorId));

        return $r;
    }
}
