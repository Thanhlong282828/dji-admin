<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class LeaveRequestPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'leave.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'leave.view', $c, 'leave_requests', $id) && ($u->can('leave.approve') || $this->owns($u, $id));
    }

    public function viewOwn(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'leave.view', $c, 'leave_requests', $id) && $this->owns($u, $id);
    }

    public function create(User $u, int $c, int $employee): bool
    {
        return $this->employee($u, 'leave.request', $c, $employee, 'leave.approve');
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
        return $this->record($u, 'leave.approve', $c, 'leave_requests', $id) && $this->status('leave_requests', $id) === 'submitted' && ! $this->owns($u, $id);
    }

    public function reject(User $u, int $c, int $id): bool
    {
        return $this->approve($u, $c, $id);
    }

    public function cancel(User $u, int $c, int $id): bool
    {
        return $this->ownAction($u, $c, $id, ['draft', 'submitted']);
    }

    public function withdraw(User $u, int $c, int $id): bool
    {
        return $this->ownAction($u, $c, $id, ['submitted']);
    }

    public function export(User $u, int $c): bool
    {
        return $this->company($u, 'leave.export', $c);
    }

    private function owns(User $u, int $id): bool
    {
        return DB::table('leave_requests as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->where('r.id', $id)->where('e.user_id', $u->id)->exists();
    }

    /** @param list<string> $states */
    private function ownAction(User $u, int $c, int $id, array $states): bool
    {
        return $this->record($u, 'leave.request', $c, 'leave_requests', $id) && $this->owns($u, $id) && in_array($this->status('leave_requests', $id), $states, true);
    }
}
