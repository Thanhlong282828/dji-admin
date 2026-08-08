<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class LeaveBalancePolicy extends AbstractWorkforcePolicy
{
    public function viewOwn(User $u, int $c, int $employee): bool
    {
        return $this->employee($u, 'leave.view', $c, $employee, 'leave.adjust-balance');
    }

    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'leave.adjust-balance', $c);
    }

    public function viewLedger(User $u, int $c, int $employee): bool
    {
        return $this->employee($u, 'leave.view', $c, $employee, 'leave.adjust-balance');
    }

    public function adjust(User $u, int $c, int $employee): bool
    {
        return $this->employee($u, 'leave.adjust-balance', $c, $employee, 'leave.adjust-balance');
    }

    public function export(User $u, int $c): bool
    {
        return $this->company($u, 'leave.export', $c);
    }
}
