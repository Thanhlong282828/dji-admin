<?php

namespace Modules\Leave\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Leave\Application\Commands\ManageLeaveCommand;
use Modules\Leave\Domain\Events\WorkforceLeaveChanged;

final readonly class LeaveManagementService
{
    public function __construct(private ConnectionInterface $db) {}

    public function execute(ManageLeaveCommand $c): int
    {
        return $this->db->transaction(function () use ($c): int {
            $id = match ($c->action) {
                'policy.create','policy.update','policy.deactivate' => $this->policy($c),'assignment.create' => $this->assignment($c),'balance.adjust','balance.opening','accrual.annual','accrual.monthly','carry_forward','expiry' => $this->ledgerAction($c),default => throw ValidationException::withMessages(['action' => 'Leave action không hợp lệ.'])
            };
            $employeeId = is_int($c->data['employee_id'] ?? null) ? $c->data['employee_id'] : 0;
            event(new WorkforceLeaveChanged($c->companyId, $id, $employeeId, $c->action, 'updated', $c->expectedVersion + 1));

            return $id;
        }, 3);
    }

    private function policy(ManageLeaveCommand $c): int
    {
        if ($c->action === 'policy.create') {
            $this->type($c->companyId, (int) $c->data['leave_type_id']);
            $duplicate = $this->db->table('leave_policies')->where('company_id', $c->companyId)->where('code', strtoupper((string) $c->data['code']))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['code' => 'Mã policy đã tồn tại.']);
            }$id = (int) $this->db->table('leave_policies')->insertGetId($this->policyData($c) + ['company_id' => $c->companyId, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $p = $this->db->table('leave_policies')->where('company_id', $c->companyId)->where('id', $c->subjectId)->lockForUpdate()->first();
            abort_if($p === null, 404);
            if ($this->int($p, 'version') !== $c->expectedVersion) {
                $this->stale();
            }$id = $this->int($p, 'id');
            $data = $c->action === 'policy.deactivate' ? ['status' => 'inactive'] : $this->policyData($c);
            $this->db->table('leave_policies')->where('id', $id)->update($data + ['version' => $c->expectedVersion + 1, 'updated_at' => now()]);
        }
        $this->log($c, $id);

        return $id;
    }

    /** @return array<string,bool|int|string|null> */
    private function policyData(ManageLeaveCommand $c): array
    {
        if ((string) ($c->data['effective_to'] ?? '') !== '' && (string) $c->data['effective_to'] < (string) $c->data['effective_from']) {
            throw ValidationException::withMessages(['effective_to' => 'Ngày kết thúc không hợp lệ.']);
        }

        return ['leave_type_id' => (int) $c->data['leave_type_id'], 'code' => strtoupper((string) $c->data['code']), 'name' => (string) $c->data['name'], 'unit' => 'minutes', 'accrual_method' => (string) $c->data['accrual_method'], 'annual_entitlement_minutes' => (int) $c->data['annual_entitlement_minutes'], 'carry_forward_rule' => json_encode(['expires_after_months' => (int) $c->data['expiry_months']]), 'maximum_carry_forward_minutes' => (int) $c->data['maximum_carry_forward_minutes'], 'negative_balance_allowed' => (bool) $c->data['negative_balance_allowed'], 'requires_attachment' => (bool) $c->data['requires_attachment'], 'approval_workflow' => json_encode(['type' => 'single_level']), 'status' => (string) $c->data['status'], 'effective_from' => (string) $c->data['effective_from'], 'effective_to' => ($c->data['effective_to'] ?? null) ?: null];
    }

    private function assignment(ManageLeaveCommand $c): int
    {
        $this->employee($c->companyId, (int) $c->data['employee_id']);
        $p = $this->policyObject($c->companyId, (int) $c->data['leave_policy_id']);
        $existing = $this->db->table('employee_leave_policy_assignments')->where('company_id', $c->companyId)->where('idempotency_key', $c->idempotencyKey)->value('id');
        if (is_numeric($existing)) {
            return (int) $existing;
        }$overlap = $this->db->table('employee_leave_policy_assignments as a')->join('leave_policies as p', 'p.id', '=', 'a.leave_policy_id')->where('a.company_id', $c->companyId)->where('a.employee_id', (int) $c->data['employee_id'])->where('p.leave_type_id', $this->int($p, 'leave_type_id'))->whereDate('a.effective_from', '<=', (string) (($c->data['effective_to'] ?? null) ?: '9999-12-31'))->where(fn (Builder $q): Builder => $q->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', (string) $c->data['effective_from']))->lockForUpdate()->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['assignment' => 'Khoảng hiệu lực bị trùng cho cùng loại phép.']);
        }$id = (int) $this->db->table('employee_leave_policy_assignments')->insertGetId(['company_id' => $c->companyId, 'employee_id' => (int) $c->data['employee_id'], 'leave_policy_id' => (int) $c->data['leave_policy_id'], 'effective_from' => $c->data['effective_from'], 'effective_to' => ($c->data['effective_to'] ?? null) ?: null, 'version' => 1, 'idempotency_key' => $c->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
        if ((int) $c->data['opening_minutes'] > 0) {
            $this->ledger($c, $this->int($p, 'leave_type_id'), 'opening', (int) $c->data['opening_minutes'], 'assignment', $id);
        } $this->log($c, $id);

        return $id;
    }

    private function ledgerAction(ManageLeaveCommand $c): int
    {
        $p = $this->policyObject($c->companyId, (int) $c->data['leave_policy_id']);
        $this->employee($c->companyId, (int) $c->data['employee_id']);
        $minutes = (int) $c->data['minutes'];
        if (in_array($c->action, ['expiry'], true)) {
            $minutes = -abs($minutes);
        }if (! in_array($c->action, ['balance.adjust', 'expiry'], true) && $minutes < 0) {
            throw ValidationException::withMessages(['minutes' => 'Số phút không được âm.']);
        }

        return $this->ledger($c, $this->int($p, 'leave_type_id'), str_replace(['balance.', 'accrual.'], '', $c->action), $minutes, 'policy', (int) $c->data['leave_policy_id']);
    }

    private function ledger(ManageLeaveCommand $c, int $leaveTypeId, string $type, int $minutes, string $referenceType, int $referenceId): int
    {
        $existing = $this->db->table('leave_balance_transactions')->where('idempotency_key', $c->idempotencyKey)->value('id');
        if (is_numeric($existing)) {
            return (int) $existing;
        }$effectiveDate = (string) ($c->data['effective_date'] ?? $c->data['effective_from'] ?? now()->toDateString());
        $year = (int) substr($effectiveDate, 0, 4);
        $balance = $this->db->table('leave_balances')->where('company_id', $c->companyId)->where('employee_id', (int) $c->data['employee_id'])->where('leave_type_id', $leaveTypeId)->where('year', $year)->lockForUpdate()->first();
        $balanceId = $balance === null ? (int) $this->db->table('leave_balances')->insertGetId(['company_id' => $c->companyId, 'employee_id' => (int) $c->data['employee_id'], 'leave_type_id' => $leaveTypeId, 'year' => $year, 'created_at' => now(), 'updated_at' => now()]) : $this->int($balance, 'id');
        $before = (int) $this->db->table('leave_balance_transactions')->where('leave_balance_id', $balanceId)->sum('minutes');
        $after = $before + $minutes;
        if ($after < 0 && $type !== 'adjust') {
            throw ValidationException::withMessages(['balance' => 'Số dư không đủ.']);
        }$id = (int) $this->db->table('leave_balance_transactions')->insertGetId(['company_id' => $c->companyId, 'employee_id' => (int) $c->data['employee_id'], 'leave_policy_id' => (int) $c->data['leave_policy_id'], 'leave_balance_id' => $balanceId, 'leave_request_id' => null, 'effective_date' => $effectiveDate, 'transaction_type' => $type, 'minutes' => $minutes, 'amount' => number_format($minutes / 480, 2, '.', ''), 'balance_before' => number_format($before / 480, 2, '.', ''), 'balance_after' => number_format($after / 480, 2, '.', ''), 'reference' => $referenceType.':'.$referenceId, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'description' => $c->data['reason'] ?? $type, 'idempotency_key' => $c->idempotencyKey, 'created_by' => $c->actorId, 'created_at' => now()]);
        $this->log($c, $id);

        return $id;
    }

    private function type(int $c, int $id): void
    {
        abort_unless($this->db->table('leave_types')->where('company_id', $c)->where('id', $id)->exists(), 404);
    }

    private function employee(int $c, int $id): void
    {
        abort_unless($this->db->table('employees')->where('company_id', $c)->where('id', $id)->exists(), 404);
    }

    private function policyObject(int $c, int $id): object
    {
        $p = $this->db->table('leave_policies')->where('company_id', $c)->where('id', $id)->first();
        abort_if($p === null, 404);

        return $p;
    }

    private function log(ManageLeaveCommand $c, int $id): void
    {
        activity('leave')->causedBy($c->actorId)->event($c->action)->withProperties(['company_id' => $c->companyId, 'subject_id' => $id])->log('Leave '.$c->action);
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['version' => 'Leave data changed. Reload before continuing.']);
    }

    private function int(object $o, string $p): int
    {
        $v = $o->{$p} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }
}
