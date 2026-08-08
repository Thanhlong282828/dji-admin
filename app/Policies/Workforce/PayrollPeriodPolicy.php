<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class PayrollPeriodPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'payroll-period.view', $c);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'payroll-period.create', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll-period.view', $c, 'payroll_periods', $id);
    }

    public function lock(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll-period.lock', $c, 'payroll_periods', $id) && $this->status('payroll_periods', $id) !== 'locked';
    }

    public function update(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll-period.update', $c, 'payroll_periods', $id) && $this->status('payroll_periods', $id) === 'draft';
    }

    public function open(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll-period.open', $c, 'payroll_periods', $id) && $this->status('payroll_periods', $id) === 'draft';
    }

    public function lockOperational(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll-period.lock', $c, 'payroll_periods', $id) && in_array($this->status('payroll_periods', $id), ['collecting', 'review'], true);
    }

    public function close(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll-period.close', $c, 'payroll_periods', $id) && $this->status('payroll_periods', $id) === 'locked';
    }

    public function delete(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll-period.delete', $c, 'payroll_periods', $id) && $this->status('payroll_periods', $id) === 'draft';
    }
}
