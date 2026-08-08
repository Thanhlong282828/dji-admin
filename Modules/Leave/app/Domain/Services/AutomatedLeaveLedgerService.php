<?php

namespace Modules\Leave\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Leave\Domain\Events\WorkforceLeaveChanged;

final readonly class AutomatedLeaveLedgerService
{
    public function __construct(private ConnectionInterface $db) {}

    public function accrue(CarbonImmutable $date): int
    {
        return $this->process($date, 'accrual');
    }

    public function carryForward(CarbonImmutable $date): int
    {
        return $this->process($date, 'carry_forward');
    }

    public function expire(CarbonImmutable $date): int
    {
        return $this->process($date, 'expiry');
    }

    private function process(CarbonImmutable $date, string $action): int
    {
        $written = 0;
        $this->db->table('employee_leave_policy_assignments as a')->join('leave_policies as p', 'p.id', '=', 'a.leave_policy_id')->where('p.status', 'active')->whereDate('a.effective_from', '<=', $date)->where(fn (Builder $q): Builder => $q->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $date))->whereDate('p.effective_from', '<=', $date)->where(fn (Builder $q): Builder => $q->whereNull('p.effective_to')->orWhereDate('p.effective_to', '>=', $date))->select('a.id', 'a.company_id', 'a.employee_id', 'p.id as policy_id', 'p.leave_type_id', 'p.accrual_method', 'p.annual_entitlement_minutes', 'p.maximum_carry_forward_minutes', 'p.carry_forward_rule')->orderBy('a.id')->chunkById(200, function ($rows) use ($date, $action, &$written): void {
            foreach ($rows as $row) {
                $written += $this->apply($row, $date, $action);
            }
        }, 'a.id', 'id');

        return $written;
    }

    private function apply(object $row, CarbonImmutable $date, string $action): int
    {
        $company = $this->int($row, 'company_id');
        $employee = $this->int($row, 'employee_id');
        $policy = $this->int($row, 'policy_id');
        $type = $this->int($row, 'leave_type_id');
        $minutes = match ($action) {
            'accrual' => $this->accrualMinutes($row, $date),
            'carry_forward' => $date->isStartOfYear() ? min($this->balance($company, $employee, $policy, $date->year - 1), $this->int($row, 'maximum_carry_forward_minutes')) : 0,
            'expiry' => $this->expiryMinutes($row, $company, $employee, $policy, $date),
            default => 0,
        };
        if ($minutes === 0) {
            return 0;
        }
        if ($action === 'expiry') {
            $minutes = -abs($minutes);
        }
        $key = $this->deterministicUuid(implode(':', ['schema-leave', $action, $company, $employee, $policy, $date->toDateString()]));

        return $this->db->transaction(function () use ($company, $employee, $policy, $type, $action, $minutes, $key, $date): int {
            $balance = $this->db->table('leave_balances')->where('company_id', $company)->where('employee_id', $employee)->where('leave_type_id', $type)->where('year', $date->year)->lockForUpdate()->first();
            $balanceId = $balance === null ? (int) $this->db->table('leave_balances')->insertGetId(['company_id' => $company, 'employee_id' => $employee, 'leave_type_id' => $type, 'year' => $date->year, 'created_at' => now(), 'updated_at' => now()]) : $this->int($balance, 'id');
            $before = $this->balance($company, $employee, $policy, $date->year);
            $inserted = $this->db->table('leave_balance_transactions')->insertOrIgnore(['company_id' => $company, 'employee_id' => $employee, 'leave_policy_id' => $policy, 'leave_balance_id' => $balanceId, 'leave_request_id' => null, 'effective_date' => $date->toDateString(), 'transaction_type' => $action, 'minutes' => $minutes, 'amount' => number_format($minutes / 480, 2, '.', ''), 'balance_before' => number_format($before / 480, 2, '.', ''), 'balance_after' => number_format(($before + $minutes) / 480, 2, '.', ''), 'reference' => 'scheduler:'.$date->toDateString(), 'reference_type' => 'leave_policy', 'reference_id' => $policy, 'description' => 'Automated '.$action, 'idempotency_key' => $key, 'created_by' => null, 'created_at' => now()]);
            if ($inserted === 1) {
                activity('leave-scheduler')->event($action)->withProperties(['company_id' => $company, 'employee_id' => $employee, 'policy_id' => $policy, 'minutes' => $minutes])->log('Automated leave '.$action);
                event(new WorkforceLeaveChanged($company, $policy, $employee, 'ledger.'.$action, 'updated', 1));
            }

            return $inserted;
        }, 3);
    }

    private function accrualMinutes(object $row, CarbonImmutable $date): int
    {
        $method = $this->text($row, 'accrual_method');
        if ($method === 'annual_grant') {
            return $date->isStartOfYear() ? $this->int($row, 'annual_entitlement_minutes') : 0;
        }

        return $date->day === 1 ? intdiv($this->int($row, 'annual_entitlement_minutes'), 12) : 0;
    }

    private function expiryMinutes(object $row, int $company, int $employee, int $policy, CarbonImmutable $date): int
    {
        $raw = $row->carry_forward_rule ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $months = is_array($decoded) && is_int($decoded['expires_after_months'] ?? null) ? $decoded['expires_after_months'] : 0;

        return $months > 0 && $date->day === 1 && $date->month === min(12, $months + 1) ? max(0, $this->balance($company, $employee, $policy, $date->year)) : 0;
    }

    private function balance(int $company, int $employee, int $policy, int $year): int
    {
        return (int) $this->db->table('leave_balance_transactions')->where('company_id', $company)->where('employee_id', $employee)->where('leave_policy_id', $policy)->whereYear('effective_date', $year)->sum('minutes');
    }

    private function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }

    private function deterministicUuid(string $seed): string
    {
        $hash = hash('sha256', $seed);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
}
