<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class OvertimeRequestPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'overtime.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.view', $c, 'overtime_requests', $id) && ($u->can('overtime.approve') || $this->owns($u, $id));
    }

    public function viewOwn(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.view', $c, 'overtime_requests', $id) && $this->owns($u, $id);
    }

    public function create(User $u, int $c, int $employee): bool
    {
        return $this->employee($u, 'overtime.request', $c, $employee, 'overtime.approve');
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->ownAction($u, $c, $id, ['draft']);
    }

    public function submit(User $u, int $c, int $id): bool
    {
        return $this->ownAction($u, $c, $id, ['draft']);
    }

    public function approve(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.approve', $c, 'overtime_requests', $id) && $this->status('overtime_requests', $id) === 'submitted' && ! $this->owns($u, $id);
    }

    public function reject(User $u, int $c, int $id): bool
    {
        return $this->approve($u, $c, $id);
    }

    public function cancel(User $u, int $c, int $id): bool
    {
        return $this->ownAction($u, $c, $id, ['draft', 'submitted']);
    }

    public function export(User $u, int $c): bool
    {
        return $this->company($u, 'overtime.export', $c);
    }

    private function owns(User $u, int $id): bool
    {
        return DB::table('overtime_requests as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->where('r.id', $id)->where('e.user_id', $u->id)->exists();
    }

    /** @param list<string> $states */
    private function ownAction(User $u, int $c, int $id, array $states): bool
    {
        return $this->record($u, 'overtime.request', $c, 'overtime_requests', $id) && $this->owns($u, $id) && in_array($this->status('overtime_requests', $id), $states, true);
    }
}
