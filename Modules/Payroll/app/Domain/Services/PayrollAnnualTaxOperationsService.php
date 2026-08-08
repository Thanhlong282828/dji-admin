<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\AnnualTaxAggregateData;
use Modules\Payroll\Application\DTOs\AnnualTaxCalculationResult;
use Modules\Payroll\Application\DTOs\AnnualTaxSupersedeResult;
use Modules\Payroll\Application\DTOs\AnnualTaxValidationResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxReconciliationStatus as Status;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxResultType as ResultType;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxValidationStage;

final readonly class PayrollAnnualTaxOperationsService
{
    public function __construct(private ConnectionInterface $db, private PayrollAnnualTaxCalculator $calculator, private PayrollAnnualTaxValidator $validator, private PayrollAnnualTaxReconciliationService $sources) {}

    public function calculate(int $companyId, int $id, int $actorId): AnnualTaxCalculationResult
    {
        return $this->db->transaction(function () use ($companyId, $id, $actorId): AnnualTaxCalculationResult {
            $row = $this->row($companyId, $id, true);
            $status = Status::from($this->string($row, 'status'));
            if ($status === Status::Calculated && $this->nullableString($row, 'calculated_source_checksum') === $this->nullableString($row, 'source_checksum')) {
                $aggregate = $this->aggregate($row);

                return new AnnualTaxCalculationResult($id, ResultType::from($this->string($row, 'result_type')), $aggregate, $this->integer($row, 'final_tax_liability_minor'), $this->integer($row, 'additional_tax_payable_minor'), $this->integer($row, 'tax_refund_minor'), $this->integer($row, 'calculation_version'), $this->string($row, 'source_checksum'));
            }
            if (! in_array($status, [Status::Draft, Status::Calculated, Status::Validated, Status::Rejected], true)) {
                throw ValidationException::withMessages(['status' => 'Annual Tax calculation is immutable after Submit.']);
            }
            if (! $this->sources->verify($companyId, $id)) {
                $this->validator->validate($companyId, $id, 'calculation');
                throw ValidationException::withMessages(['checksum' => 'Annual Tax source checksum mismatch.']);
            }
            $aggregate = $this->aggregate($row);
            foreach ([$aggregate->grossIncomeMinor, $aggregate->taxableIncomeBeforeDeductionsMinor, $aggregate->nonTaxableIncomeMinor, $aggregate->employeeInsuranceDeductionMinor, $aggregate->personalDeductionMinor, $aggregate->dependentDeductionMinor, $aggregate->otherDeductionMinor, $aggregate->pitWithheldMinor] as $amount) {
                if ($amount < 0) {
                    throw ValidationException::withMessages(['totals' => 'Signed source deltas produced a negative annual category total.']);
                }
            }
            $policy = $this->policy($this->string($row, 'tax_policy_snapshot'));
            $liability = $this->calculator->liability($aggregate, $policy);
            $difference = $liability - $aggregate->pitWithheldMinor - $aggregate->priorTaxSettlementMinor;
            $result = $difference > 0 ? ResultType::Payable : ($difference < 0 ? ResultType::Refund : ResultType::Balanced);
            $payable = max(0, $difference);
            $refund = max(0, -$difference);
            $version = $this->integer($row, 'calculation_version') + 1;
            $checksum = $this->string($row, 'source_checksum');
            $this->db->table('payroll_annual_tax_reconciliations')->where('id', $id)->update(['status' => Status::Calculated->value, 'result_type' => $result->value, 'calculation_version' => $version, 'calculator_version' => PayrollAnnualTaxCalculator::VERSION, 'calculated_source_checksum' => $checksum, 'gross_income_minor' => $aggregate->grossIncomeMinor, 'taxable_income_minor' => $aggregate->taxableIncomeAfterDeductionsMinor, 'non_taxable_income_minor' => $aggregate->nonTaxableIncomeMinor, 'employee_insurance_deduction_minor' => $aggregate->employeeInsuranceDeductionMinor, 'personal_deduction_minor' => $aggregate->personalDeductionMinor, 'dependent_deduction_minor' => $aggregate->dependentDeductionMinor, 'other_deduction_minor' => $aggregate->otherDeductionMinor, 'pit_withheld_minor' => $aggregate->pitWithheldMinor, 'prior_tax_settlement_minor' => $aggregate->priorTaxSettlementMinor, 'final_tax_liability_minor' => $liability, 'additional_tax_payable_minor' => $payable, 'tax_refund_minor' => $refund, 'calculated_by' => $actorId, 'calculated_at' => now(), 'updated_at' => now()]);
            $this->history($row, $status, Status::Calculated, $status === Status::Calculated ? 'recalculated' : 'calculated', $actorId, ['calculation_version' => $version, 'calculator_version' => PayrollAnnualTaxCalculator::VERSION]);

            return new AnnualTaxCalculationResult($id, $result, $aggregate, $liability, $payable, $refund, $version, $checksum);
        }, 3);
    }

    public function validate(int $companyId, int $id, int $actorId, PayrollAnnualTaxValidationStage $stage = PayrollAnnualTaxValidationStage::PreSubmit): AnnualTaxValidationResult
    {
        return $this->db->transaction(function () use ($companyId, $id, $actorId, $stage): AnnualTaxValidationResult {
            $row = $this->row($companyId, $id, true);
            $status = Status::from($this->string($row, 'status'));
            if ($status !== Status::Calculated) {
                throw ValidationException::withMessages(['status' => 'Only a calculated Annual Tax reconciliation can be validated.']);
            }
            $result = $this->validator->validate($companyId, $id, $stage);
            if ($result->valid) {
                $this->db->table('payroll_annual_tax_reconciliations')->where('id', $id)->update(['status' => Status::Validated->value, 'updated_at' => now()]);
                $this->history($row, $status, Status::Validated, 'validated', $actorId, ['blocking' => 0, 'warnings' => $result->warningCount]);
            }

            return $result;
        }, 3);
    }

    public function supersede(int $companyId, int $id, int $actorId, string $reason, ?string $idempotencyKey = null): AnnualTaxSupersedeResult
    {
        return $this->db->transaction(function () use ($companyId, $id, $actorId, $reason, $idempotencyKey): AnnualTaxSupersedeResult {
            if ($idempotencyKey !== null) {
                $existing = $this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing instanceof \stdClass) {
                    return new AnnualTaxSupersedeResult($id, $this->integer($existing, 'id'), $this->integer($existing, 'version'));
                }
            }
            $old = $this->row($companyId, $id, true);
            if (Status::from($this->string($old, 'status')) !== Status::Finalized) {
                throw ValidationException::withMessages(['status' => 'Only finalized Annual Tax reconciliation can be superseded.']);
            }
            $version = $this->integer($old, 'version') + 1;
            $employeeId = $this->integer($old, 'employee_id');
            $taxYear = $this->integer($old, 'tax_year');
            $this->db->table('payroll_annual_tax_reconciliations')->where('id', $id)->update(['active_key' => null, 'status' => Status::Superseded->value, 'superseded_at' => now(), 'updated_at' => now()]);
            $newId = (int) $this->db->table('payroll_annual_tax_reconciliations')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'employee_id' => $employeeId, 'tax_year' => $taxYear, 'jurisdiction' => $this->string($old, 'jurisdiction'), 'currency' => $this->string($old, 'currency'), 'reference' => $this->string($old, 'reference').'-V'.$version, 'version' => $version, 'active_key' => $employeeId.':'.$taxYear, 'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(), 'status' => Status::Draft->value, 'taxpayer_snapshot' => $this->string($old, 'taxpayer_snapshot'), 'company_tax_snapshot' => $this->string($old, 'company_tax_snapshot'), 'tax_policy_snapshot' => $this->nullableString($old, 'tax_policy_snapshot'), 'checksum_version' => $this->string($old, 'checksum_version'), 'created_by' => $actorId, 'supersedes_reconciliation_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            $this->db->table('payroll_annual_tax_reconciliations')->where('id', $id)->update(['active_key' => null, 'status' => Status::Superseded->value, 'superseded_by_reconciliation_id' => $newId, 'superseded_at' => now(), 'updated_at' => now()]);
            $this->history($old, Status::Finalized, Status::Superseded, 'superseded', $actorId, ['new_reconciliation_id' => $newId, 'reason' => $reason]);
            $this->db->table('payroll_annual_tax_histories')->insert(['annual_tax_reconciliation_id' => $newId, 'company_id' => $companyId, 'employee_id' => $employeeId, 'to_status' => Status::Draft->value, 'action' => 'created_from_supersede', 'actor_id' => $actorId, 'safe_reason' => $reason, 'metadata' => json_encode(['old_reconciliation_id' => $id], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return new AnnualTaxSupersedeResult($id, $newId, $version);
        }, 3);
    }

    private function aggregate(object $row): AnnualTaxAggregateData
    {
        $totals = $this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $this->integer($row, 'id'))->selectRaw('COUNT(*) source_count, COALESCE(SUM(gross_income_minor),0) gross, COALESCE(SUM(taxable_income_minor),0) taxable, COALESCE(SUM(non_taxable_income_minor),0) non_taxable, COALESCE(SUM(employee_insurance_deduction_minor),0) insurance, COALESCE(SUM(personal_deduction_minor),0) personal, COALESCE(SUM(dependent_deduction_minor),0) dependent, COALESCE(SUM(other_deduction_minor),0) other_deduction, COALESCE(SUM(pit_withheld_minor),0) withheld')->first();
        if (! $totals instanceof \stdClass || $this->integer($totals, 'source_count') === 0) {
            throw ValidationException::withMessages(['sources' => 'No eligible Annual Tax sources were collected.']);
        }
        $after = $this->integer($totals, 'taxable') - $this->integer($totals, 'insurance') - $this->integer($totals, 'personal') - $this->integer($totals, 'dependent') - $this->integer($totals, 'other_deduction');

        return new AnnualTaxAggregateData($this->integer($totals, 'gross'), $this->integer($totals, 'taxable'), $this->integer($totals, 'non_taxable'), $this->integer($totals, 'insurance'), $this->integer($totals, 'personal'), $this->integer($totals, 'dependent'), $this->integer($totals, 'other_deduction'), $after, $this->integer($totals, 'withheld'), $this->integer($row, 'prior_tax_settlement_minor'), $this->integer($totals, 'source_count'), $this->db->table('payroll_annual_tax_components')->where('annual_tax_reconciliation_id', $this->integer($row, 'id'))->count(), $this->string($row, 'currency'), $this->integer($row, 'tax_year'), $this->string($row, 'jurisdiction'));
    }

    /** @return array{tax_method:string,rounding_rule:string,floor_at_zero:bool,brackets:list<array{sequence:int,lower_minor:int,upper_minor:int|null,rate_basis_points:int}>} */
    private function policy(string $json): array
    {
        $raw = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($raw) || ($raw['residency_group'] ?? null) !== 'resident') {
            throw ValidationException::withMessages(['residency' => 'Only resident Annual Tax policy snapshots are supported.']);
        }
        $brackets = [];
        foreach (is_array($raw['brackets'] ?? null) ? $raw['brackets'] : [] as $bracket) {
            if (is_array($bracket)) {
                $brackets[] = ['sequence' => $this->arrayInteger($bracket, 'sequence'), 'lower_minor' => $this->arrayInteger($bracket, 'lower_minor'), 'upper_minor' => $this->arrayNullableInteger($bracket, 'upper_minor'), 'rate_basis_points' => $this->arrayInteger($bracket, 'rate_basis_points')];
            }
        }
        if ($brackets === []) {
            throw ValidationException::withMessages(['policy' => 'Annual progressive bracket snapshot is missing.']);
        }

        return ['tax_method' => is_string($raw['tax_method'] ?? null) ? $raw['tax_method'] : '', 'rounding_rule' => is_string($raw['rounding_rule'] ?? null) ? $raw['rounding_rule'] : 'half_up', 'floor_at_zero' => (bool) ($raw['floor_at_zero'] ?? true), 'brackets' => $brackets];
    }

    private function row(int $companyId, int $id, bool $lock): \stdClass
    {
        $query = $this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $row instanceof \stdClass) {
            abort(404);
        }

        return $row;
    }

    /** @param array<string, int|string> $metadata */
    private function history(object $row, Status $from, Status $to, string $action, int $actorId, array $metadata): void
    {
        $this->db->table('payroll_annual_tax_histories')->insert(['annual_tax_reconciliation_id' => $this->integer($row, 'id'), 'company_id' => $this->integer($row, 'company_id'), 'employee_id' => $this->integer($row, 'employee_id'), 'from_status' => $from->value, 'to_status' => $to->value, 'action' => $action, 'actor_id' => $actorId, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }

    private function nullableString(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<mixed> $values */
    private function arrayInteger(array $values, string $key): int
    {
        return $this->arrayNullableInteger($values, $key) ?? 0;
    }

    /** @param array<mixed> $values */
    private function arrayNullableInteger(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
