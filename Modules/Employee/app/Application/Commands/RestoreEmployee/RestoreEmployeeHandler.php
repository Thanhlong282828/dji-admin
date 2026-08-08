<?php

namespace Modules\Employee\Application\Commands\RestoreEmployee;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Employee\Domain\Events\EmployeeRestored;
use Modules\Employee\Domain\Repositories\EmployeeWriteRepositoryInterface;

final readonly class RestoreEmployeeHandler
{
    public function __construct(private EmployeeWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(RestoreEmployeeCommand $c): array
    {
        $e = $this->repo->restore($c->companyId, $c->id);
        $this->events->dispatch(new EmployeeRestored($c->companyId, $e, $c->actorId));

        return $e;
    }
}
