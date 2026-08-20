<?php

namespace Modules\Employee\Application\Commands\DeleteEmployee;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Employee\Domain\Events\EmployeeDeleted;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Repositories\EmployeeWriteRepositoryInterface;
use Modules\Employee\Domain\Services\EmployeeAccountService;

final readonly class DeleteEmployeeHandler
{
    public function __construct(private EmployeeWriteRepositoryInterface $repo, private Dispatcher $events, private EmployeeAccountService $accounts) {}

    public function handle(DeleteEmployeeCommand $c): void
    {
        DB::transaction(function () use ($c): void {
            $employee = Employee::query()->where('company_id', $c->companyId)->lockForUpdate()->findOrFail($c->id);
            if ($employee->user_id !== null) {
                $this->accounts->setAccess($c->companyId, $c->id, false, $c->actorId);
            }
            $this->repo->delete($c->companyId, $c->id);
            $this->events->dispatch(new EmployeeDeleted($c->companyId, ['id' => $c->id], $c->actorId));
        }, 3);
    }
}
