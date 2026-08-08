<?php

namespace Modules\Organization\Application\Commands\CreateJobPosition;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\JobPositionCreated;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class CreateJobPositionHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(CreateJobPositionCommand $c): array
    {
        $r = $this->repo->createPosition($c->companyId, $c->data);
        $this->events->dispatch(new JobPositionCreated($c->companyId, $r, $c->actorId));

        return $r;
    }
}
