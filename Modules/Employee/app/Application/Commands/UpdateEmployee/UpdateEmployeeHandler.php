<?php

namespace Modules\Employee\Application\Commands\UpdateEmployee;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Employee\Domain\Events\EmployeeUpdated;
use Modules\Employee\Domain\Repositories\EmployeeWriteRepositoryInterface;

final readonly class UpdateEmployeeHandler
{
    public function __construct(private EmployeeWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(UpdateEmployeeCommand $c): array
    {
        $e = $this->repo->update($c->companyId, $c->id, $c->data);
        $this->events->dispatch(new EmployeeUpdated($c->companyId, $e, $c->actorId));

        return $e;
    }
}
