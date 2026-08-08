<?php

namespace App\Policies\Workforce;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class PayrollRunPolicy extends AbstractWorkforcePolicy
{
    public function viewAny(User $u, int $c): bool
    {
        return $this->company($u, 'payroll.run.view', $c);
    }

    public function view(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll.run.view', $c, 'payroll_runs', $id);
    }

    public function create(User $u, int $c): bool
    {
        return $this->company($u, 'payroll.run.create', $c);
    }

    public function calculate(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'payroll.run.calculate', $c, $id, ['draft', 'calculated', 'validation_failed', 'failed', 'rejected']);
    }

    public function recalculate(User $u, int $c, int $id): bool
    {
        return $this->calculate($u, $c, $id);
    }

    public function submit(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'payroll.run.submit', $c, $id, ['calculated']);
    }

    public function approve(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'payroll.run.approve', $c, $id, ['submitted']) && ! in_array($u->id, [$this->actor($id, 'created_by'), $this->actor($id, 'submitted_by')], true);
    }

    public function reject(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'payroll.run.reject', $c, $id, ['submitted']);
    }

    public function finalize(User $u, int $c, int $id): bool
    {
        return $this->action($u, 'payroll.run.finalize', $c, $id, ['approved']);
    }

    public function export(User $u, int $c, int $id): bool
    {
        return $this->record($u, 'payroll.export', $c, 'payroll_runs', $id);
    }

    /** @param list<string> $states */
    private function action(User $u, string $p, int $c, int $id, array $states): bool
    {
        return $this->record($u, $p, $c, 'payroll_runs', $id) && in_array($this->status('payroll_runs', $id), $states, true);
    }

    private function actor(int $id, string $column): int
    {
        $v = DB::table('payroll_runs')->where('id', $id)->value($column);

        return is_numeric($v) ? (int) $v : 0;
    }
}
