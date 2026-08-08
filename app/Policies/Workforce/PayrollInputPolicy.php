<?php

namespace App\Policies\Workforce;

use App\Models\User;

final readonly class PayrollInputPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'payroll-period.view', $c);
    }

    public function viewReadiness(User $u, int $c, int $period): bool
    {
        return $this->record($u, 'payroll-period.view', $c, 'payroll_periods', $period);
    }

    public function generate(User $u, int $c, int $period): bool
    {
        return $this->record($u, 'payroll-input.generate', $c, 'payroll_periods', $period) && $this->status('payroll_periods', $period) !== 'locked';
    }

    public function viewSnapshot(User $u, int $c, int $snapshot): bool
    {
        return $this->record($u, 'payroll-period.view', $c, 'payroll_input_snapshots', $snapshot);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'payroll-input.generate', $c);
    }

    public function validate(User $u, int $c, int $snapshot): bool
    {
        return $this->record($u, 'payroll-input.validate', $c, 'payroll_input_snapshots', $snapshot) && in_array($this->status('payroll_input_snapshots', $snapshot), ['draft', 'blocked', 'ready'], true);
    }

    public function createVersion(User $u, int $c, int $snapshot): bool
    {
        return $this->record($u, 'payroll-input.create-version', $c, 'payroll_input_snapshots', $snapshot) && in_array($this->status('payroll_input_snapshots', $snapshot), ['blocked', 'ready'], true);
    }

    public function createRun(User $u, int $c, int $snapshot): bool
    {
        return $this->record($u, 'payroll.run.create', $c, 'payroll_input_snapshots', $snapshot) && $this->status('payroll_input_snapshots', $snapshot) === 'ready';
    }

    public function export(User $u, int $c): bool
    {
        return $this->company($u, 'payroll-input.export', $c);
    }
}
