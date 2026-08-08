<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class OvertimeReconciliationPolicy extends AbstractWorkforcePolicy
{
    public function viewCalculation(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.view', $c, 'overtime_requests', $id);
    }

    public function reconcile(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'overtime.approve', $c, 'overtime_requests', $id) && $this->status('overtime_requests', $id) === 'approved';
    }
}
