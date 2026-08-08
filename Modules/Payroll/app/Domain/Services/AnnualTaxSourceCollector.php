<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Modules\Payroll\Application\DTOs\AnnualTaxCollectionData;
use Modules\Payroll\Application\DTOs\AnnualTaxComponentData;
use Modules\Payroll\Application\DTOs\AnnualTaxSourceData;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxComponentCategory;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxIssueCode;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxSourceType;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxValidationSeverity;

final readonly class AnnualTaxSourceCollector
{
    public function __construct(private ConnectionInterface $db, private PayrollAnnualTaxAttributionResolver $attribution) {}

    public function collect(int $companyId, int $employeeId, int $taxYear, string $currency): AnnualTaxCollectionData
    {
        $sources = [];
        $issues = [];
        $payrollRows = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->join('payroll_periods as p', 'p.id', '=', 'r.payroll_period_id')->where('i.company_id', $companyId)->where('i.employee_id', $employeeId)->where('r.company_id', $companyId)->where('r.status', 'finalized')->whereYear('p.period_end', $taxYear)->select(['i.*', 'r.id as source_run_id', 'r.status as run_status', 'p.id as source_period_id', 'p.period_end'])->orderBy('p.period_end')->orderBy('i.id')->get();
        foreach ($payrollRows as $row) {
            $version = $this->i($row, 'calculation_version');
            $reference = 'payroll-item:'.$this->i($row, 'id').':v'.$version;
            if ($version < 1) {
                $issues[] = $this->issue(PayrollAnnualTaxIssueCode::SourceCalculationVersionMissing, $reference, 'Finalized Payroll source has no calculation version.');

                continue;
            }
            $rowCurrency = $this->s($row, 'currency_code');
            if ($rowCurrency !== $currency) {
                $issues[] = $this->issue(PayrollAnnualTaxIssueCode::CurrencyMismatch, $reference, 'Payroll source currency does not match Annual Tax scope.');

                continue;
            }
            $periodYear = $this->attribution->regular($this->s($row, 'period_end'));
            $components = $this->regularComponents($companyId, $this->i($row, 'id'), $version, $rowCurrency);
            $deductions = $this->deductions($components);
            $gross = $this->i($row, 'gross_amount_minor');
            $taxable = $this->i($row, 'taxable_income_minor');
            $payslip = $this->db->table('payroll_payslips')->where('company_id', $companyId)->where('payroll_item_id', $this->i($row, 'id'))->where('calculation_version', $version)->whereNotIn('status', ['revoked'])->orderByDesc('payslip_version')->value('id');
            $sources[] = new AnnualTaxSourceData(PayrollAnnualTaxSourceType::RegularPayroll, $reference, $periodYear, (int) substr($this->s($row, 'period_end'), 5, 2), $periodYear, $this->i($row, 'source_period_id'), $this->i($row, 'source_run_id'), $this->i($row, 'id'), is_numeric($payslip) ? (int) $payslip : null, null, null, $version, $this->ni($row, 'tax_policy_id'), $this->ni($row, 'tax_policy_version'), $gross, $taxable, max(0, $gross - $taxable), $this->i($row, 'insurance_employee_total_minor'), $deductions['personal'], $deductions['dependent'], $deductions['other'], $this->i($row, 'personal_income_tax_minor'), $rowCurrency, sprintf('%04d-%02d-regular-%020d', $periodYear, (int) substr($this->s($row, 'period_end'), 5, 2), $this->i($row, 'id')), $components);
        }

        $missingAttribution = $this->db->table('payroll_adjustment_requests')->where('company_id', $companyId)->where('employee_id', $employeeId)->where('status', 'applied')->whereNull('tax_attribution_year')->pluck('adjustment_number');
        foreach ($missingAttribution as $number) {
            $reference = is_string($number) ? $number : 'adjustment';
            $issues[] = $this->issue(PayrollAnnualTaxIssueCode::MissingAdjustmentTaxAttributionYear, $reference, 'Applied Adjustment is missing explicit tax attribution year.');
        }
        $adjustments = $this->db->table('payroll_adjustment_requests as a')->join('payroll_adjustment_runs as ar', 'ar.adjustment_request_id', '=', 'a.id')->join('payroll_periods as p', 'p.id', '=', 'a.source_payroll_period_id')->where('a.company_id', $companyId)->where('a.employee_id', $employeeId)->where('a.status', 'applied')->where('ar.status', 'applied')->where('a.tax_attribution_year', $taxYear)->select(['a.id', 'a.adjustment_number', 'a.source_payroll_period_id', 'a.source_payroll_run_id', 'a.source_payroll_item_id', 'a.tax_attribution_year', 'ar.id as adjustment_run_id', 'ar.source_calculation_version', 'ar.delta_calculation_version', 'ar.delta_gross_minor', 'ar.delta_employee_insurance_minor', 'ar.delta_personal_income_tax_minor', 'ar.currency', 'p.period_end'])->orderBy('p.period_end')->orderBy('a.id')->get();
        foreach ($adjustments as $row) {
            $attribution = $this->attribution->adjustment($this->ni($row, 'tax_attribution_year'));
            if ($attribution === null) {
                continue;
            }
            $reference = 'adjustment:'.$this->i($row, 'id').':v'.$this->i($row, 'delta_calculation_version');
            $rowCurrency = $this->s($row, 'currency');
            if ($rowCurrency !== $currency) {
                $issues[] = $this->issue(PayrollAnnualTaxIssueCode::CurrencyMismatch, $reference, 'Adjustment source currency does not match Annual Tax scope.');

                continue;
            }
            $components = $this->adjustmentComponents($companyId, $this->i($row, 'id'), $this->i($row, 'delta_calculation_version'), $rowCurrency);
            $taxable = 0;
            foreach ($components as $component) {
                if ($component->category === PayrollAnnualTaxComponentCategory::TaxableIncome) {
                    $taxable += $component->amountMinor;
                }
            }
            $periodYear = $this->attribution->regular($this->s($row, 'period_end'));
            $sources[] = new AnnualTaxSourceData(PayrollAnnualTaxSourceType::RetroactiveAdjustment, $reference, $periodYear, (int) substr($this->s($row, 'period_end'), 5, 2), $attribution, $this->i($row, 'source_payroll_period_id'), $this->i($row, 'source_payroll_run_id'), $this->i($row, 'source_payroll_item_id'), null, $this->i($row, 'id'), $this->i($row, 'adjustment_run_id'), $this->i($row, 'delta_calculation_version'), null, $this->i($row, 'source_calculation_version'), $this->i($row, 'delta_gross_minor'), $taxable, $this->i($row, 'delta_gross_minor') - $taxable, $this->i($row, 'delta_employee_insurance_minor'), 0, 0, 0, $this->i($row, 'delta_personal_income_tax_minor'), $rowCurrency, sprintf('%04d-%02d-adjustment-%020d', $attribution, (int) substr($this->s($row, 'period_end'), 5, 2), $this->i($row, 'id')), $components);
        }
        usort($sources, static fn (AnnualTaxSourceData $a, AnnualTaxSourceData $b): int => [$a->sortKey, $a->reference] <=> [$b->sortKey, $b->reference]);
        if ($sources === []) {
            $issues[] = $this->issue(PayrollAnnualTaxIssueCode::NoEligibleSources, null, 'No eligible finalized Payroll or applied Adjustment source exists for this Annual Tax scope.');
        }

        return new AnnualTaxCollectionData($sources, $issues);
    }

    /** @return list<AnnualTaxComponentData> */
    private function regularComponents(int $companyId, int $itemId, int $version, string $currency): array
    {
        $items = [];
        foreach ($this->db->table('payroll_item_components')->where('company_id', $companyId)->where('payroll_item_id', $itemId)->where('version', $version)->whereIn('component_code_snapshot', ['TAXABLE_INCOME', 'TAX_DEDUCTION_INSURANCE', 'TAX_DEDUCTION_PERSONAL', 'TAX_DEDUCTION_DEPENDENT', 'TAX_DEDUCTION_OTHER'])->orWhere(fn (Builder $query): Builder => $query->where('company_id', $companyId)->where('payroll_item_id', $itemId)->where('version', $version)->where('source_type', 'tax_policy_bracket'))->orderBy('calculation_order')->get() as $row) {
            $code = $this->s($row, 'component_code_snapshot');
            $category = match (true) {
                $code === 'TAXABLE_INCOME' => PayrollAnnualTaxComponentCategory::TaxableIncome,
                $code === 'TAX_DEDUCTION_INSURANCE' => PayrollAnnualTaxComponentCategory::EmployeeInsuranceDeduction,
                $code === 'TAX_DEDUCTION_PERSONAL' => PayrollAnnualTaxComponentCategory::PersonalDeduction,
                $code === 'TAX_DEDUCTION_DEPENDENT' => PayrollAnnualTaxComponentCategory::DependentDeduction,
                $code === 'TAX_DEDUCTION_OTHER' => PayrollAnnualTaxComponentCategory::OtherDeduction,
                str_starts_with($code, 'PIT_BRACKET_') => PayrollAnnualTaxComponentCategory::PitWithheld,
                default => PayrollAnnualTaxComponentCategory::TaxableIncome,
            };
            $items[] = new AnnualTaxComponentData($category, $code, $this->s($row, 'component_name_snapshot'), $this->i($row, 'amount_minor'), $currency, $version, $this->i($row, 'id'), null, ['source_type' => $this->s($row, 'source_type')], $this->i($row, 'calculation_order'));
        }

        return $items;
    }

    /** @return list<AnnualTaxComponentData> */
    private function adjustmentComponents(int $companyId, int $adjustmentId, int $version, string $currency): array
    {
        $items = [];
        $order = 1;
        foreach ($this->db->table('payroll_adjustment_components')->where('company_id', $companyId)->where('adjustment_request_id', $adjustmentId)->orderBy('component_code')->get() as $row) {
            $code = $this->s($row, 'component_code');
            $category = (bool) ($row->taxable ?? false) ? PayrollAnnualTaxComponentCategory::TaxableIncome : PayrollAnnualTaxComponentCategory::NonTaxableIncome;
            if ($code === 'PERSONAL_INCOME_TAX') {
                $category = PayrollAnnualTaxComponentCategory::PitWithheld;
            } elseif ($code === 'EMPLOYEE_INSURANCE') {
                $category = PayrollAnnualTaxComponentCategory::EmployeeInsuranceDeduction;
            }
            $items[] = new AnnualTaxComponentData($category, $code, $this->s($row, 'component_type'), $this->i($row, 'delta_amount_minor'), $currency, $version, null, $this->i($row, 'id'), ['taxable' => (bool) ($row->taxable ?? false)], $order++);
        }

        return $items;
    }

    /**
     * @param  list<AnnualTaxComponentData>  $components
     * @return array{personal:int, dependent:int, other:int}
     */
    private function deductions(array $components): array
    {
        $result = ['personal' => 0, 'dependent' => 0, 'other' => 0];
        foreach ($components as $component) {
            if ($component->category === PayrollAnnualTaxComponentCategory::PersonalDeduction) {
                $result['personal'] += $component->amountMinor;
            } elseif ($component->category === PayrollAnnualTaxComponentCategory::DependentDeduction) {
                $result['dependent'] += $component->amountMinor;
            } elseif ($component->category === PayrollAnnualTaxComponentCategory::OtherDeduction) {
                $result['other'] += $component->amountMinor;
            }
        }

        return $result;
    }

    /** @return array{code:PayrollAnnualTaxIssueCode,severity:PayrollAnnualTaxValidationSeverity,field:string|null,reference:string|null,message:string} */
    private function issue(PayrollAnnualTaxIssueCode $code, ?string $reference, string $message): array
    {
        return ['code' => $code, 'severity' => PayrollAnnualTaxValidationSeverity::Blocking, 'field' => null, 'reference' => $reference, 'message' => $message];
    }

    private function i(object $row, string $key): int
    {
        return $this->ni($row, $key) ?? 0;
    }

    private function ni(object $row, string $key): ?int
    {
        $value = $row->{$key} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function s(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
