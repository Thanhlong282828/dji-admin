<?php

namespace Modules\Employee\Application\Commands\CreateEmployee;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Employee\Domain\Events\EmployeeCreated;
use Modules\Employee\Domain\Repositories\EmployeeWriteRepositoryInterface;

final readonly class CreateEmployeeHandler
{
    public function __construct(private EmployeeWriteRepositoryInterface $repo, private Dispatcher $events) {}

    /** @return array<string, mixed> */
    public function handle(CreateEmployeeCommand $c): array
    {
        $e = $this->repo->create($c->companyId, $c->data);
        $this->events->dispatch(new EmployeeCreated($c->companyId, $e, $c->actorId));

        return $e;
    }
}
