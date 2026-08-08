<?php

namespace Modules\Employee\Application\Commands\DeleteEmployee;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Employee\Domain\Events\EmployeeDeleted;
use Modules\Employee\Domain\Repositories\EmployeeWriteRepositoryInterface;

final readonly class DeleteEmployeeHandler
{
    public function __construct(private EmployeeWriteRepositoryInterface $repo, private Dispatcher $events) {}

    public function handle(DeleteEmployeeCommand $c): void
    {
        $this->repo->delete($c->companyId, $c->id);
        $this->events->dispatch(new EmployeeDeleted($c->companyId, ['id' => $c->id], $c->actorId));
    }
}
