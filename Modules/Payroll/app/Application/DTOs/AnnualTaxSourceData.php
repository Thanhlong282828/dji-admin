<?php

namespace Modules\Payroll\Application\DTOs;

use Modules\Payroll\Domain\Enums\PayrollAnnualTaxSourceType;

final readonly class AnnualTaxSourceData
{
    /** @param list<AnnualTaxComponentData> $components */
    public function __construct(public PayrollAnnualTaxSourceType $type, public string $reference, public int $periodYear, public ?int $periodMonth, public int $taxAttributionYear, public ?int $payrollPeriodId, public ?int $payrollRunId, public ?int $payrollItemId, public ?int $payslipId, public ?int $adjustmentRequestId, public ?int $adjustmentRunId, public int $calculationVersion, public ?int $taxPolicyId, public ?int $taxPolicyVersion, public int $grossMinor, public int $taxableMinor, public int $nonTaxableMinor, public int $insuranceMinor, public int $personalDeductionMinor, public int $dependentDeductionMinor, public int $otherDeductionMinor, public int $pitWithheldMinor, public string $currency, public string $sortKey, public array $components) {}

    /**
     * @return array{
     *     type:string, reference:string, period_year:int, period_month:int|null,
     *     tax_attribution_year:int, payroll_period_id:int|null, payroll_run_id:int|null,
     *     payroll_item_id:int|null, payslip_id:int|null, adjustment_request_id:int|null,
     *     adjustment_run_id:int|null, calculation_version:int, tax_policy_id:int|null,
     *     tax_policy_version:int|null, gross_minor:int, taxable_minor:int,
     *     non_taxable_minor:int, insurance_minor:int, personal_deduction_minor:int,
     *     dependent_deduction_minor:int, other_deduction_minor:int, pit_withheld_minor:int,
     *     currency:string, sort_key:string,
     *     components:list<array{
     *         category:string, code:string, amount_minor:int, currency:string,
     *         calculation_version:int, payroll_item_component_id:int|null,
     *         adjustment_component_id:int|null,
     *         metadata:array<string, bool|int|string|null>, sort_order:int
     *     }>
     * }
     */
    public function checksumPayload(): array
    {
        return ['type' => $this->type->value, 'reference' => $this->reference, 'period_year' => $this->periodYear, 'period_month' => $this->periodMonth, 'tax_attribution_year' => $this->taxAttributionYear, 'payroll_period_id' => $this->payrollPeriodId, 'payroll_run_id' => $this->payrollRunId, 'payroll_item_id' => $this->payrollItemId, 'payslip_id' => $this->payslipId, 'adjustment_request_id' => $this->adjustmentRequestId, 'adjustment_run_id' => $this->adjustmentRunId, 'calculation_version' => $this->calculationVersion, 'tax_policy_id' => $this->taxPolicyId, 'tax_policy_version' => $this->taxPolicyVersion, 'gross_minor' => $this->grossMinor, 'taxable_minor' => $this->taxableMinor, 'non_taxable_minor' => $this->nonTaxableMinor, 'insurance_minor' => $this->insuranceMinor, 'personal_deduction_minor' => $this->personalDeductionMinor, 'dependent_deduction_minor' => $this->dependentDeductionMinor, 'other_deduction_minor' => $this->otherDeductionMinor, 'pit_withheld_minor' => $this->pitWithheldMinor, 'currency' => $this->currency, 'sort_key' => $this->sortKey, 'components' => array_map(static fn (AnnualTaxComponentData $component): array => $component->checksumPayload(), $this->components)];
    }
}
