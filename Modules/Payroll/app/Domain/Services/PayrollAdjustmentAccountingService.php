<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\AdjustmentAccountingResult;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentDownstreamStatus;
use Modules\Payroll\Domain\Enums\PayrollDownstreamSourceType;
use stdClass;

final readonly class PayrollAdjustmentAccountingService
{
    public function __construct(private ConnectionInterface $db, private PayrollDownstreamSourceInvariant $invariant) {}

    public function create(int $companyId, int $adjustmentId, int $actorId): AdjustmentAccountingResult
    {
        return $this->db->transaction(function () use ($companyId, $adjustmentId, $actorId): AdjustmentAccountingResult {
            $intent = $this->intent($companyId, $adjustmentId);
            $intentId = $this->i($intent, 'id');
            $existing = $this->ni($intent, 'accounting_journal_id') ?? $this->numeric($this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->value('id'));
            if ($existing !== null) {
                return $this->result($intentId, $existing, true);
            }
            $source = $this->db->table('payroll_adjustment_requests as a')->join('payroll_adjustment_runs as ar', 'ar.adjustment_request_id', '=', 'a.id')->join('payroll_periods as p', 'p.id', '=', 'a.source_payroll_period_id')->where('a.company_id', $companyId)->where('a.id', $adjustmentId)->where('a.status', 'applied')->lockForUpdate()->select(['a.*', 'ar.id as adjustment_run_id', 'ar.currency', 'ar.delta_calculation_version', 'p.period_end'])->first();
            if (! is_object($source)) {
                throw ValidationException::withMessages(['adjustment' => 'ADJUSTMENT_NOT_APPLIED']);
            }
            $this->invariant->assertAccounting(PayrollDownstreamSourceType::RetroactiveAdjustment, null, $adjustmentId, $this->i($source, 'adjustment_run_id'));
            $policy = $this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->where('status', 'active')->where('currency', $this->s($source, 'currency'))->where('effective_from', '<=', $this->s($source, 'period_end'))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $this->s($source, 'period_end')))->orderByDesc('version')->first();
            if (! is_object($policy)) {
                throw ValidationException::withMessages(['accounting' => 'ACCOUNTING_POLICY_MISSING']);
            }
            $mappings = $this->db->table('payroll_accounting_mappings as m')->leftJoin('accounting_accounts as d', 'd.id', '=', 'm.debit_account_id')->leftJoin('accounting_accounts as c', 'c.id', '=', 'm.credit_account_id')->where('m.company_id', $companyId)->where('m.accounting_policy_id', $this->i($policy, 'id'))->where('m.status', 'active')->where('m.effective_from', '<=', $this->s($source, 'period_end'))->where(fn (Builder $q): Builder => $q->whereNull('m.effective_to')->orWhere('m.effective_to', '>=', $this->s($source, 'period_end')))->select(['m.*', 'd.code as debit_code', 'd.name as debit_name', 'd.status as debit_status', 'd.allow_posting as debit_postable', 'd.currency as debit_currency', 'c.code as credit_code', 'c.name as credit_name', 'c.status as credit_status', 'c.allow_posting as credit_postable', 'c.currency as credit_currency'])->get();
            $components = $this->db->table('payroll_adjustment_components')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->where('delta_amount_minor', '<>', 0)->orderBy('id')->get();
            if ($components->isEmpty()) {
                throw ValidationException::withMessages(['accounting' => 'ADJUSTMENT_COMPONENTS_MISSING']);
            }
            $journalId = (int) $this->db->table('payroll_accounting_journals')->insertGetId([
                'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'payroll_run_id' => null,
                'journal_source_type' => PayrollDownstreamSourceType::RetroactiveAdjustment->value,
                'adjustment_request_id' => $adjustmentId, 'adjustment_run_id' => $this->i($source, 'adjustment_run_id'),
                'source_payroll_run_id' => $this->i($source, 'source_payroll_run_id'), 'source_payroll_item_id' => $this->i($source, 'source_payroll_item_id'),
                'source_calculation_version' => $this->i($source, 'source_calculation_version'), 'delta_calculation_version' => $this->i($source, 'delta_calculation_version'),
                'payroll_period_id' => $this->i($source, 'source_payroll_period_id'), 'accounting_policy_id' => $this->i($policy, 'id'), 'policy_version' => $this->i($policy, 'version'),
                'journal_number' => 'ADJ-JRN-'.$adjustmentId, 'journal_date' => now()->toDateString(), 'posting_date' => now()->toDateString(),
                'resolution_date' => $this->s($source, 'period_end'), 'currency' => $this->s($source, 'currency'),
                'calculation_version' => $this->i($source, 'delta_calculation_version'), 'status' => 'draft',
                'active_adjustment_journal_key' => 'ADJ-JRN:'.$adjustmentId, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $line = 0;
            $debit = 0;
            $credit = 0;
            foreach ($components as $component) {
                foreach ($this->entries($component) as $entry) {
                    $mapping = $this->mapping($mappings, $entry['type'], $entry['code']);
                    $amount = abs($entry['amount']);
                    $normalDebit = $this->ni($mapping, 'debit_account_id') !== null;
                    $isDebit = $entry['amount'] > 0 ? $normalDebit : ! $normalDebit;
                    $accountId = $this->i($mapping, $normalDebit ? 'debit_account_id' : 'credit_account_id');
                    $accountCode = $this->s($mapping, $normalDebit ? 'debit_code' : 'credit_code');
                    $accountName = $this->s($mapping, $normalDebit ? 'debit_name' : 'credit_name');
                    if ($accountId <= 0 || $amount <= 0) {
                        throw ValidationException::withMessages(['accounting' => 'ACCOUNTING_ADJUSTMENT_MAPPING_INVALID']);
                    }
                    $line++;
                    $lineId = (int) $this->db->table('payroll_accounting_journal_lines')->insertGetId([
                        'uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'journal_id' => $journalId, 'line_number' => $line,
                        'source_type' => $entry['type'], 'source_code' => $entry['code'], 'description' => $entry['description'],
                        'account_id' => $accountId, 'account_code_snapshot' => $accountCode, 'account_name_snapshot' => $accountName,
                        'debit_minor' => $isDebit ? $amount : 0, 'credit_minor' => $isDebit ? 0 : $amount,
                        'currency' => $this->s($source, 'currency'), 'mapping_id' => $this->i($mapping, 'id'),
                        'mapping_snapshot' => json_encode(['mapping_id' => $this->i($mapping, 'id'), 'source' => 'retroactive_adjustment'], JSON_THROW_ON_ERROR),
                        'source_count' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->db->table('payroll_accounting_journal_sources')->insert([
                        'company_id' => $companyId, 'journal_id' => $journalId, 'journal_line_id' => $lineId, 'payroll_item_id' => null,
                        'employee_id' => $this->i($source, 'employee_id'), 'calculation_version' => $this->i($source, 'delta_calculation_version'),
                        'source_type' => $entry['type'], 'downstream_source_type' => PayrollDownstreamSourceType::RetroactiveAdjustment->value,
                        'source_code' => $entry['code'], 'source_amount_minor' => $amount,
                        'adjustment_request_id' => $adjustmentId, 'adjustment_run_id' => $this->i($source, 'adjustment_run_id'),
                        'adjustment_component_id' => $this->i($component, 'id'), 'source_payroll_run_id' => $this->i($source, 'source_payroll_run_id'),
                        'source_payroll_item_id' => $this->i($source, 'source_payroll_item_id'),
                        'historical_amount_minor' => $this->i($component, 'historical_amount_minor'), 'corrected_amount_minor' => $this->i($component, 'corrected_amount_minor'),
                        'delta_amount_minor' => $entry['amount'], 'source_calculation_version' => $this->i($source, 'source_calculation_version'),
                        'delta_calculation_version' => $this->i($source, 'delta_calculation_version'), 'created_at' => now(),
                    ]);
                    $debit += $isDebit ? $amount : 0;
                    $credit += $isDebit ? 0 : $amount;
                }
            }
            if ($debit !== $credit || $debit <= 0) {
                throw ValidationException::withMessages(['accounting' => 'ACCOUNTING_DEBIT_CREDIT_IMBALANCE']);
            }
            $this->db->table('payroll_accounting_journals')->where('id', $journalId)->update(['total_debit_minor' => $debit, 'total_credit_minor' => $credit, 'line_count' => $line, 'updated_at' => now()]);
            activity('payroll-adjustment')->causedBy($actorId)->event('accounting_created')->withProperties(['company_id' => $companyId, 'adjustment_id' => $adjustmentId, 'journal_id' => $journalId])->log('Adjustment Accounting Journal created');

            return new AdjustmentAccountingResult($intentId, PayrollAdjustmentDownstreamStatus::Completed, $journalId, 'ADJ-JRN-'.$adjustmentId, $debit, $credit, true, false);
        }, 3);
    }

    /** @return list<array{type:string,code:string,description:string,amount:int}> */
    private function entries(object $component): array
    {
        $amount = $this->i($component, 'delta_amount_minor');

        return match ($this->s($component, 'component_code')) {
            'GROSS' => [['type' => 'adjustment_gross', 'code' => 'GROSS_ADJUSTMENT', 'description' => 'Retroactive gross payroll adjustment', 'amount' => $amount]],
            'EMPLOYEE_INSURANCE' => [['type' => 'adjustment_employee_insurance', 'code' => 'EMPLOYEE_INSURANCE_ADJUSTMENT', 'description' => 'Retroactive employee insurance adjustment', 'amount' => $amount]],
            'EMPLOYER_INSURANCE' => [
                ['type' => 'adjustment_employer_insurance_expense', 'code' => 'EMPLOYER_INSURANCE_EXPENSE_ADJUSTMENT', 'description' => 'Retroactive employer insurance expense', 'amount' => $amount],
                ['type' => 'adjustment_employer_insurance_liability', 'code' => 'EMPLOYER_INSURANCE_LIABILITY_ADJUSTMENT', 'description' => 'Retroactive employer insurance liability', 'amount' => $amount],
            ],
            'PERSONAL_INCOME_TAX' => [['type' => 'adjustment_personal_income_tax', 'code' => 'PIT_ADJUSTMENT', 'description' => 'Retroactive personal income tax adjustment', 'amount' => $amount]],
            'NET' => [['type' => 'adjustment_net_pay', 'code' => 'NET_PAY_ADJUSTMENT', 'description' => 'Retroactive net pay adjustment', 'amount' => $amount]],
            default => [['type' => 'adjustment_component', 'code' => $this->s($component, 'component_code').'_ADJUSTMENT', 'description' => 'Retroactive Payroll component adjustment', 'amount' => $amount]],
        };
    }

    /** @param Collection<int, stdClass> $mappings */
    private function mapping(Collection $mappings, string $type, string $code): object
    {
        $mapping = $mappings->first(fn (object $row): bool => $this->s($row, 'source_type') === $type && $this->s($row, 'source_code') === $code);
        if (! is_object($mapping)) {
            throw ValidationException::withMessages(['accounting' => 'ACCOUNTING_MAPPING_MISSING: '.$type.'/'.$code]);
        }

        return $mapping;
    }

    private function intent(int $companyId, int $adjustmentId): object
    {
        $row = $this->db->table('payroll_adjustment_downstream_intents')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->whereIn('intent_type', ['accounting', 'accounting_eligible'])->lockForUpdate()->first();
        if (! is_object($row)) {
            throw ValidationException::withMessages(['accounting' => 'ACCOUNTING_INTENT_MISSING']);
        }

        return $row;
    }

    private function result(int $intentId, int $journalId, bool $existing): AdjustmentAccountingResult
    {
        $j = $this->db->table('payroll_accounting_journals')->where('id', $journalId)->first();
        if (! is_object($j)) {
            throw new \RuntimeException('Adjustment Journal reference is invalid.');
        }

        return new AdjustmentAccountingResult($intentId, PayrollAdjustmentDownstreamStatus::Completed, $journalId, $this->s($j, 'journal_number'), $this->i($j, 'total_debit_minor'), $this->i($j, 'total_credit_minor'), $this->i($j, 'total_debit_minor') === $this->i($j, 'total_credit_minor'), $existing);
    }

    private function numeric(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function i(object $row, string $key): int
    {
        return $this->numeric($row->{$key} ?? null) ?? 0;
    }

    private function ni(object $row, string $key): ?int
    {
        return $this->numeric($row->{$key} ?? null);
    }

    private function s(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
