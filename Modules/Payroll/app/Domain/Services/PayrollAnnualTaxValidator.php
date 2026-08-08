<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\AnnualTaxValidationResult;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxIssueCode as Code;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxResultType;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxValidationStage;

final readonly class PayrollAnnualTaxValidator
{
    public function __construct(private ConnectionInterface $db, private PayrollAnnualTaxReconciliationService $sources) {}

    public function validate(int $companyId, int $id, PayrollAnnualTaxValidationStage|string $stage): AnnualTaxValidationResult
    {
        $stage = $stage instanceof PayrollAnnualTaxValidationStage ? $stage->value : $stage;
        $row = $this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('id', $id)->first();
        if (! $row instanceof \stdClass) {
            abort(404);
        }
        $this->resolveStageIssues($id, $stage);
        $taxpayer = $this->json($row->taxpayer_snapshot ?? null);
        $policy = $this->json($row->tax_policy_snapshot ?? null);
        $sourceCount = $this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $id)->count();
        if ($sourceCount === 0) {
            $this->issue($row, Code::NoEligibleSources, $stage, 'No eligible persisted Annual Tax source exists.');
        }
        if ($this->blank($taxpayer['tax_identifier'] ?? null)) {
            $this->issue($row, Code::MissingTaxIdentifier, $stage, 'Tax identifier snapshot is missing.', 'tax_identifier');
        }
        if ($this->blank($taxpayer['tax_residency'] ?? null)) {
            $this->issue($row, Code::MissingTaxResidency, $stage, 'Tax residency snapshot is missing.', 'tax_residency');
        }
        if ($policy === []) {
            $this->issue($row, Code::MissingTaxPolicySnapshot, $stage, 'Immutable tax policy snapshot is missing.', 'tax_policy_snapshot');
        }
        if ($policy !== [] && ($policy['residency_group'] ?? null) !== 'resident') {
            $this->issue($row, Code::UnsupportedTaxResidency, $stage, 'Only resident annual progressive calculation is supported.', 'residency_group');
        }
        if (! array_key_exists('deductions', $policy)) {
            $this->issue($row, Code::MissingPersonalDeductionSnapshot, $stage, 'Personal deduction policy snapshot is missing.', 'deductions');
        }
        $dependentDeduction = false;
        foreach (is_array($policy['deductions'] ?? null) ? $policy['deductions'] : [] as $deduction) {
            if (is_array($deduction) && ($deduction['type'] ?? null) === 'dependent') {
                $dependentDeduction = true;
                break;
            }
        }
        if ($dependentDeduction && ($taxpayer['dependent_evidence_verified'] ?? false) !== true) {
            $this->issue($row, Code::MissingDependentEvidence, $stage, 'Dependent deduction evidence is missing from the immutable taxpayer snapshot.', 'dependent_evidence');
        }
        if (! $this->sources->verify($companyId, $id)) {
            $this->issue($row, Code::SourceChecksumMismatch, $stage, 'Persisted Annual Tax source checksum does not match.');
        }
        if (($row->calculated_source_checksum ?? null) !== null && $row->calculated_source_checksum !== $row->source_checksum) {
            $this->issue($row, Code::CalculationStale, $stage, 'Calculation source checksum is stale.');
        }
        if ($this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $id)->where(fn (Builder $query): Builder => $query->whereNull('source_calculation_version')->orWhere('source_calculation_version', '<', 1))->exists()) {
            $this->issue($row, Code::SourceCalculationVersionMissing, $stage, 'A persisted source has no calculation version.');
        }
        if ($this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $id)->where('currency', '<>', $this->string($row, 'currency'))->exists()) {
            $this->issue($row, Code::CurrencyMismatch, $stage, 'A source currency differs from the reconciliation currency.');
        }
        if ($this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $id)->where('tax_attribution_year', '<>', $this->int($row, 'tax_year'))->exists()) {
            $this->issue($row, Code::TaxYearMismatch, $stage, 'A source attribution year differs from the reconciliation tax year.');
        }
        if ($this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $id)->selectRaw('source_type, source_reference, COUNT(*) total')->groupBy('source_type', 'source_reference')->havingRaw('COUNT(*) > 1')->exists()) {
            $this->issue($row, Code::DuplicateSource, $stage, 'Duplicate persisted source detected.');
        }
        foreach (['gross_income_minor', 'taxable_income_minor', 'non_taxable_income_minor', 'employee_insurance_deduction_minor', 'personal_deduction_minor', 'dependent_deduction_minor', 'other_deduction_minor', 'pit_withheld_minor'] as $column) {
            $total = $this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $id)->sum($column);
            if ($total < 0) {
                $this->issue($row, Code::NegativeAnnualCategoryTotal, $stage, 'Signed source deltas produced a negative annual category total.', $column);
                break;
            }
        }
        if (in_array($stage, ['pre_submit', 'pre_review', 'pre_approve', 'pre_finalize'], true)) {
            $this->validateResult($row, $stage);
        }
        $blocking = $this->db->table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $id)->whereNull('resolved_at')->where('severity', 'blocking')->count();
        $warnings = $this->db->table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $id)->whereNull('resolved_at')->where('severity', 'warning')->count();

        return new AnnualTaxValidationResult($id, $blocking, $warnings, $blocking === 0);
    }

    private function validateResult(\stdClass $row, string $stage): void
    {
        $version = $this->int($row, 'calculation_version');
        $liability = $this->int($row, 'final_tax_liability_minor');
        $withheld = $this->int($row, 'pit_withheld_minor');
        $prior = $this->int($row, 'prior_tax_settlement_minor');
        $payable = $this->int($row, 'additional_tax_payable_minor');
        $refund = $this->int($row, 'tax_refund_minor');
        $type = $this->string($row, 'result_type');
        if ($version < 1) {
            $this->issue($row, Code::MissingCalculationVersion, $stage, 'Calculation version is missing.');
        }
        if ($payable > 0 && $refund > 0) {
            $this->issue($row, Code::PayableRefundConflict, $stage, 'Payable and refund cannot both be positive.');
        }
        if (($type === PayrollAnnualTaxResultType::Payable->value && ($payable <= 0 || $refund !== 0)) || ($type === PayrollAnnualTaxResultType::Refund->value && ($refund <= 0 || $payable !== 0)) || ($type === PayrollAnnualTaxResultType::Balanced->value && ($payable !== 0 || $refund !== 0))) {
            $this->issue($row, Code::InvalidResultTotals, $stage, 'Result type and settlement totals are inconsistent.');
        }
        if ($liability - $withheld - $prior !== $payable - $refund) {
            $this->issue($row, Code::InvalidSettlementEquation, $stage, 'Annual settlement equation is inconsistent.');
        }
    }

    private function resolveStageIssues(int $id, string $stage): void
    {
        $this->db->table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $id)->where('validation_stage', $stage)->whereNull('resolved_at')->update(['resolved_at' => now(), 'updated_at' => now()]);
    }

    private function issue(\stdClass $row, Code $code, string $stage, string $message, ?string $field = null): void
    {
        $id = $this->int($row, 'id');
        $fingerprint = hash('sha256', implode('|', [(string) $id, '', $code->value, $stage, $field ?? '']));
        $this->db->table('payroll_annual_tax_validation_issues')->updateOrInsert(['annual_tax_reconciliation_id' => $id, 'fingerprint' => $fingerprint], ['company_id' => $this->int($row, 'company_id'), 'employee_id' => $this->int($row, 'employee_id'), 'issue_code' => $code->value, 'severity' => 'blocking', 'validation_stage' => $stage, 'field_name' => $field, 'safe_message' => $message, 'resolved_at' => null, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** @return array<string,mixed> */
    private function json(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return [];
        }
        $result = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function blank(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }

    private function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
