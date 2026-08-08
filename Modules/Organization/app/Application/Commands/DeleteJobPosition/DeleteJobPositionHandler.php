<?php

namespace Modules\Organization\Application\Commands\DeleteJobPosition;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Organization\Domain\Events\JobPositionDeleted;
use Modules\Organization\Domain\Repositories\OrganizationWriteRepositoryInterface;

final readonly class DeleteJobPositionHandler
{
    public function __construct(private OrganizationWriteRepositoryInterface $repo, private Dispatcher $events) {}

    public function handle(DeleteJobPositionCommand $c): void
    {
        $this->repo->deletePosition($c->companyId, $c->id);
        $this->events->dispatch(new JobPositionDeleted($c->companyId, ['id' => $c->id], $c->actorId));
    }
}
