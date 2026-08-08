<?php

namespace Modules\Payroll\Domain\Enums;

enum PayrollAnnualTaxIssueCode: string
{
    case MissingTaxIdentifier = 'missing_tax_identifier';
    case MissingTaxResidency = 'missing_tax_residency';
    case MissingTaxPolicySnapshot = 'missing_tax_policy_snapshot';
    case MissingPersonalDeductionSnapshot = 'missing_personal_deduction_snapshot';
    case MissingDependentSnapshotDetail = 'missing_dependent_snapshot_detail';
    case MissingDependentEvidence = 'missing_dependent_evidence';
    case UnsupportedMultipleEmployerCase = 'unsupported_multiple_employer_case';
    case SourceCalculationVersionMissing = 'source_calculation_version_missing';
    case SourceChecksumMismatch = 'source_checksum_mismatch';
    case CurrencyMismatch = 'currency_mismatch';
    case JurisdictionMismatch = 'jurisdiction_mismatch';
    case TaxYearMismatch = 'tax_year_mismatch';
    case UnfinalizedPayrollSource = 'unfinalized_payroll_source';
    case UnappliedAdjustmentSource = 'unapplied_adjustment_source';
    case MissingAdjustmentTaxAttributionYear = 'missing_adjustment_tax_attribution_year';
    case DuplicateSource = 'duplicate_source';
    case NoEligibleSources = 'no_eligible_sources';
    case NegativeAnnualCategoryTotal = 'negative_annual_category_total';
    case UnsupportedTaxResidency = 'unsupported_tax_residency';
    case CalculationStale = 'calculation_stale';
    case InvalidResultTotals = 'invalid_result_totals';
    case InvalidSettlementEquation = 'invalid_settlement_equation';
    case PayableRefundConflict = 'payable_refund_conflict';
    case MissingCalculationVersion = 'missing_calculation_version';
}
