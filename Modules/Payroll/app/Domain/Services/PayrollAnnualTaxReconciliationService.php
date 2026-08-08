<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\DTOs\AnnualTaxComponentData;
use Modules\Payroll\Application\DTOs\AnnualTaxDraftData;
use Modules\Payroll\Application\DTOs\AnnualTaxSourceCollectionResult;
use Modules\Payroll\Application\DTOs\AnnualTaxSourceData;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxComponentCategory;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxIssueCode;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxReconciliationStatus;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxSourceType;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxValidationSeverity;
use Modules\Payroll\Domain\Models\PayrollAnnualTaxReconciliation;
use Modules\Payroll\Domain\Repositories\AnnualTaxReconciliationRepositoryInterface;

final readonly class PayrollAnnualTaxReconciliationService
{
    public function __construct(private ConnectionInterface $db, private AnnualTaxReconciliationRepositoryInterface $repository, private AnnualTaxSourceCollector $collector, private AnnualTaxChecksumService $checksum) {}

    public function create(CreateAnnualTaxReconciliationCommand $command): AnnualTaxDraftData
    {
        return $this->db->transaction(function () use ($command): AnnualTaxDraftData {
            $existing = $this->repository->findByIdempotency($command->companyId, $command->idempotencyKey);
            if ($existing !== null) {
                return $this->draft($existing, true);
            }
            $employee = $this->db->table('employees')->where('company_id', $command->companyId)->where('id', $command->employeeId)->lockForUpdate()->first();
            $company = $this->db->table('companies')->where('id', $command->companyId)->first();
            if (! is_object($employee) || ! is_object($company)) {
                throw ValidationException::withMessages(['employee' => 'Employee is not available in the Annual Tax company scope.']);
            }
            if ($this->repository->findActive($command->companyId, $command->employeeId, $command->taxYear) !== null) {
                throw ValidationException::withMessages(['reconciliation' => 'An active Annual Tax reconciliation already exists for this Company, Employee and tax year.']);
            }
            $taxpayer = $this->taxpayerSnapshot($command->companyId, $command->employeeId, $command->taxYear, $employee);
            $companySnapshot = ['company_id' => $command->companyId, 'company_code' => $this->s($company, 'code'), 'legal_name' => $this->s($company, 'name'), 'tax_identifier' => $this->n($company, 'tax_code'), 'address' => $this->n($company, 'address'), 'jurisdiction' => $command->jurisdiction];
            $policy = $this->policySnapshot($command->companyId, $command->employeeId, $command->taxYear);
            $uuid = (string) Str::uuid();
            $activeKey = $command->employeeId.':'.$command->taxYear;
            try {
                $id = (int) $this->db->table('payroll_annual_tax_reconciliations')->insertGetId(['uuid' => $uuid, 'company_id' => $command->companyId, 'employee_id' => $command->employeeId, 'tax_year' => $command->taxYear, 'jurisdiction' => $command->jurisdiction, 'currency' => $command->currency, 'reference' => sprintf('ATR-%d-%d-%s', $command->taxYear, $command->employeeId, strtoupper(substr(str_replace('-', '', $uuid), 0, 8))), 'version' => 1, 'active_key' => $activeKey, 'idempotency_key' => $command->idempotencyKey, 'status' => PayrollAnnualTaxReconciliationStatus::Draft->value, 'taxpayer_snapshot' => $this->json($taxpayer), 'company_tax_snapshot' => $this->json($companySnapshot), 'tax_policy_snapshot' => $policy === null ? null : $this->json($policy), 'checksum_version' => AnnualTaxChecksumService::VERSION, 'created_by' => $command->actorId, 'created_at' => now(), 'updated_at' => now()]);
            } catch (QueryException $exception) {
                $retry = $this->repository->findByIdempotency($command->companyId, $command->idempotencyKey);
                if ($retry !== null) {
                    return $this->draft($retry, true);
                }
                throw $exception;
            }
            $this->db->table('payroll_annual_tax_histories')->insert(['annual_tax_reconciliation_id' => $id, 'company_id' => $command->companyId, 'employee_id' => $command->employeeId, 'to_status' => PayrollAnnualTaxReconciliationStatus::Draft->value, 'action' => 'created', 'actor_id' => $command->actorId, 'metadata' => $this->json(['version' => 1]), 'created_at' => now()]);

            return $this->draft($this->repository->findScoped($command->companyId, $id), false);
        }, 3);
    }

    public function collect(int $companyId, int $reconciliationId, int $actorId): AnnualTaxSourceCollectionResult
    {
        return $this->db->transaction(function () use ($companyId, $reconciliationId, $actorId): AnnualTaxSourceCollectionResult {
            $reconciliation = $this->repository->findScoped($companyId, $reconciliationId, true);
            $status = $reconciliation->statusValue();
            if (! in_array($status, [PayrollAnnualTaxReconciliationStatus::Draft, PayrollAnnualTaxReconciliationStatus::Calculated], true)) {
                throw ValidationException::withMessages(['reconciliation' => 'Annual Tax sources are immutable after Submit.']);
            }
            $existing = $reconciliation->source_checksum !== null;
            $data = $this->collector->collect($companyId, $reconciliation->employee_id, $reconciliation->tax_year, $reconciliation->currency);
            $this->db->table('payroll_annual_tax_components')->where('annual_tax_reconciliation_id', $reconciliationId)->delete();
            $this->db->table('payroll_annual_tax_validation_issues')->where('annual_tax_reconciliation_id', $reconciliationId)->delete();
            $this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $reconciliationId)->delete();
            $componentCount = 0;
            foreach ($data->sources as $source) {
                $sourceId = (int) $this->db->table('payroll_annual_tax_sources')->insertGetId(['annual_tax_reconciliation_id' => $reconciliationId, 'company_id' => $companyId, 'employee_id' => $reconciliation->employee_id, 'source_type' => $source->type->value, 'source_reference' => $source->reference, 'source_period_year' => $source->periodYear, 'source_period_month' => $source->periodMonth, 'tax_attribution_year' => $source->taxAttributionYear, 'payroll_period_id' => $source->payrollPeriodId, 'payroll_run_id' => $source->payrollRunId, 'payroll_item_id' => $source->payrollItemId, 'payslip_id' => $source->payslipId, 'adjustment_request_id' => $source->adjustmentRequestId, 'adjustment_run_id' => $source->adjustmentRunId, 'source_calculation_version' => $source->calculationVersion, 'source_tax_policy_id' => $source->taxPolicyId, 'source_tax_policy_version' => $source->taxPolicyVersion, 'gross_income_minor' => $source->grossMinor, 'taxable_income_minor' => $source->taxableMinor, 'non_taxable_income_minor' => $source->nonTaxableMinor, 'employee_insurance_deduction_minor' => $source->insuranceMinor, 'personal_deduction_minor' => $source->personalDeductionMinor, 'dependent_deduction_minor' => $source->dependentDeductionMinor, 'other_deduction_minor' => $source->otherDeductionMinor, 'pit_withheld_minor' => $source->pitWithheldMinor, 'currency' => $source->currency, 'source_checksum' => $this->checksum->source($source), 'source_sort_key' => $source->sortKey, 'created_at' => now(), 'updated_at' => now()]);
                foreach ($source->components as $component) {
                    $this->db->table('payroll_annual_tax_components')->insert(['annual_tax_reconciliation_id' => $reconciliationId, 'annual_tax_source_id' => $sourceId, 'company_id' => $companyId, 'employee_id' => $reconciliation->employee_id, 'category' => $component->category->value, 'component_code' => $component->code, 'description' => $component->description, 'amount_minor' => $component->amountMinor, 'currency' => $component->currency, 'source_calculation_version' => $component->calculationVersion, 'payroll_item_component_id' => $component->payrollItemComponentId, 'adjustment_component_id' => $component->adjustmentComponentId, 'metadata' => $this->json($component->metadata), 'sort_order' => $component->sortOrder, 'created_at' => now(), 'updated_at' => now()]);
                    $componentCount++;
                }
            }
            $issues = $data->issues;
            $taxpayer = $reconciliation->taxpayerSnapshotValue();
            $taxIdentifier = $this->arrayNullableString($taxpayer, 'tax_identifier');
            if ($taxIdentifier === null || $taxIdentifier === '') {
                $issues[] = $this->issue(PayrollAnnualTaxIssueCode::MissingTaxIdentifier, 'tax_identifier', 'Tax identifier was not available when the immutable taxpayer snapshot was created.');
            }
            $taxResidency = $this->arrayNullableString($taxpayer, 'tax_residency');
            if ($taxResidency === null || $taxResidency === 'unknown') {
                $issues[] = $this->issue(PayrollAnnualTaxIssueCode::MissingTaxResidency, 'tax_residency', 'Tax residency was not available when the immutable taxpayer snapshot was created.');
            }
            if ($reconciliation->policySnapshotValue() === null) {
                $issues[] = $this->issue(PayrollAnnualTaxIssueCode::MissingTaxPolicySnapshot, 'tax_policy_snapshot', 'Annual Tax policy snapshot is missing.');
            }
            foreach ($data->sources as $source) {
                if ($source->dependentDeductionMinor !== 0) {
                    $issues[] = $this->issue(PayrollAnnualTaxIssueCode::MissingDependentSnapshotDetail, 'dependent_deduction_minor', 'Dependent deduction amount is immutable, but historical dependent identity/evidence detail is unavailable.', $source->reference);
                }
            }
            $warning = 0;
            $blocking = 0;
            foreach ($issues as $issue) {
                $warning += $issue['severity'] === PayrollAnnualTaxValidationSeverity::Warning ? 1 : 0;
                $blocking += $issue['severity'] === PayrollAnnualTaxValidationSeverity::Blocking ? 1 : 0;
                $this->db->table('payroll_annual_tax_validation_issues')->insert(['annual_tax_reconciliation_id' => $reconciliationId, 'company_id' => $companyId, 'employee_id' => $reconciliation->employee_id, 'issue_code' => $issue['code']->value, 'severity' => $issue['severity']->value, 'field_name' => $issue['field'], 'source_reference' => $issue['reference'], 'safe_message' => $issue['message'], 'created_at' => now(), 'updated_at' => now()]);
            }
            $annualChecksum = $this->checksum->reconciliation($companyId, $reconciliation->employee_id, $reconciliation->tax_year, $reconciliation->jurisdiction, $reconciliation->currency, $reconciliation->version, $data->sources);
            $this->db->table('payroll_annual_tax_reconciliations')->where('id', $reconciliationId)->update(['source_checksum' => $annualChecksum, 'calculation_version' => ($reconciliation->calculation_version ?? 0) + 1, 'status' => PayrollAnnualTaxReconciliationStatus::Draft->value, 'result_type' => null, 'calculator_version' => null, 'calculated_source_checksum' => null, 'final_tax_liability_minor' => 0, 'additional_tax_payable_minor' => 0, 'tax_refund_minor' => 0, 'calculated_by' => null, 'calculated_at' => null, 'updated_at' => now()]);
            $this->db->table('payroll_annual_tax_histories')->insert(['annual_tax_reconciliation_id' => $reconciliationId, 'company_id' => $companyId, 'employee_id' => $reconciliation->employee_id, 'from_status' => $status->value, 'to_status' => $status->value, 'action' => $existing ? 'sources_recollected' : 'sources_collected', 'actor_id' => $actorId, 'metadata' => $this->json(['source_count' => count($data->sources), 'component_count' => $componentCount, 'checksum_version' => AnnualTaxChecksumService::VERSION, 'warning_count' => $warning, 'blocking_count' => $blocking]), 'created_at' => now()]);

            return new AnnualTaxSourceCollectionResult($reconciliationId, count($data->sources), $componentCount, $warning, $blocking, $annualChecksum, $existing, $existing);
        }, 3);
    }

    public function verify(int $companyId, int $reconciliationId): bool
    {
        $reconciliation = $this->repository->findScoped($companyId, $reconciliationId);
        $sources = $this->hydrateSources($companyId, $reconciliationId);
        foreach ($sources as $source) {
            $stored = $this->db->table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $reconciliationId)->where('source_reference', $source->reference)->value('source_checksum');
            if (! is_string($stored) || ! hash_equals($stored, $this->checksum->source($source))) {
                return false;
            }
        }
        $expected = $this->checksum->reconciliation($companyId, $reconciliation->employee_id, $reconciliation->tax_year, $reconciliation->jurisdiction, $reconciliation->currency, $reconciliation->version, $sources);

        return is_string($reconciliation->source_checksum) && hash_equals($reconciliation->source_checksum, $expected);
    }

    /** @return list<AnnualTaxSourceData> */
    private function hydrateSources(int $companyId, int $reconciliationId): array
    {
        $sources = [];
        foreach ($this->db->table('payroll_annual_tax_sources')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $reconciliationId)->orderBy('source_sort_key')->get() as $row) {
            $components = [];
            foreach ($this->db->table('payroll_annual_tax_components')->where('company_id', $companyId)->where('annual_tax_source_id', $this->i($row, 'id'))->orderBy('sort_order')->get() as $component) {
                $components[] = new AnnualTaxComponentData(PayrollAnnualTaxComponentCategory::from($this->s($component, 'category')), $this->s($component, 'component_code'), $this->n($component, 'description'), $this->i($component, 'amount_minor'), $this->s($component, 'currency'), $this->i($component, 'source_calculation_version'), $this->ni($component, 'payroll_item_component_id'), $this->ni($component, 'adjustment_component_id'), $this->metadata($this->n($component, 'metadata')), $this->i($component, 'sort_order'));
            }
            $sources[] = new AnnualTaxSourceData(PayrollAnnualTaxSourceType::from($this->s($row, 'source_type')), $this->s($row, 'source_reference'), $this->i($row, 'source_period_year'), $this->ni($row, 'source_period_month'), $this->i($row, 'tax_attribution_year'), $this->ni($row, 'payroll_period_id'), $this->ni($row, 'payroll_run_id'), $this->ni($row, 'payroll_item_id'), $this->ni($row, 'payslip_id'), $this->ni($row, 'adjustment_request_id'), $this->ni($row, 'adjustment_run_id'), $this->i($row, 'source_calculation_version'), $this->ni($row, 'source_tax_policy_id'), $this->ni($row, 'source_tax_policy_version'), $this->i($row, 'gross_income_minor'), $this->i($row, 'taxable_income_minor'), $this->i($row, 'non_taxable_income_minor'), $this->i($row, 'employee_insurance_deduction_minor'), $this->i($row, 'personal_deduction_minor'), $this->i($row, 'dependent_deduction_minor'), $this->i($row, 'other_deduction_minor'), $this->i($row, 'pit_withheld_minor'), $this->s($row, 'currency'), $this->s($row, 'source_sort_key'), $components);
        }

        return $sources;
    }

    /** @return array{employee_id:int,employee_code:string,display_name:string,tax_identifier:string|null,tax_residency:string|null,employment_scope:string} */
    private function taxpayerSnapshot(int $companyId, int $employeeId, int $taxYear, object $employee): array
    {
        $profile = $this->db->table('employee_tax_profiles')->where('company_id', $companyId)->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $taxYear.'-12-31')->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $taxYear.'-01-01'))->orderByDesc('effective_from')->first();

        return ['employee_id' => $employeeId, 'employee_code' => $this->s($employee, 'employee_code'), 'display_name' => $this->s($employee, 'full_name'), 'tax_identifier' => is_object($profile) ? $this->n($profile, 'tax_identification_number') : null, 'tax_residency' => is_object($profile) ? $this->n($profile, 'residency_status') : null, 'employment_scope' => 'employer_scoped'];
    }

    /** @return array<string, array<int, array<string, int|string|null>>|bool|int|string|null>|null */
    private function policySnapshot(int $companyId, int $employeeId, int $taxYear): ?array
    {
        $policy = $this->db->table('employee_tax_profiles as ep')->join('tax_policies as p', 'p.id', '=', 'ep.tax_policy_id')->where('ep.company_id', $companyId)->where('ep.employee_id', $employeeId)->whereDate('ep.effective_from', '<=', $taxYear.'-12-31')->where(fn (Builder $query): Builder => $query->whereNull('ep.effective_to')->orWhereDate('ep.effective_to', '>=', $taxYear.'-01-01'))->orderByDesc('ep.effective_from')->select('p.*')->first();
        if (! is_object($policy)) {
            return null;
        }
        $deductions = [];
        foreach ($this->db->table('tax_policy_deductions')->where('tax_policy_id', $this->i($policy, 'id'))->orderBy('priority')->get() as $row) {
            $deductions[] = ['code' => $this->s($row, 'code'), 'type' => $this->s($row, 'deduction_type'), 'amount_minor' => $this->ni($row, 'amount_minor')];
        }
        $brackets = [];
        foreach ($this->db->table('tax_policy_brackets')->where('tax_policy_id', $this->i($policy, 'id'))->orderBy('sequence')->get() as $row) {
            $brackets[] = ['sequence' => $this->i($row, 'sequence'), 'lower_minor' => $this->i($row, 'lower_bound_minor'), 'upper_minor' => $this->ni($row, 'upper_bound_minor'), 'rate_basis_points' => $this->i($row, 'rate_basis_points')];
        }

        return ['id' => $this->i($policy, 'id'), 'version' => $this->i($policy, 'version'), 'code' => $this->s($policy, 'code'), 'effective_from' => $this->s($policy, 'effective_from'), 'effective_to' => $this->n($policy, 'effective_to'), 'currency' => $this->s($policy, 'currency_code'), 'residency_group' => $this->s($policy, 'residency_group'), 'tax_method' => $this->s($policy, 'tax_method'), 'rounding_rule' => $this->s($policy, 'rounding_rule'), 'floor_at_zero' => (bool) ($policy->floor_taxable_income_at_zero ?? false), 'deductions' => $deductions, 'brackets' => $brackets];
    }

    /** @return array{code:PayrollAnnualTaxIssueCode,severity:PayrollAnnualTaxValidationSeverity,field:string|null,reference:string|null,message:string} */
    private function issue(PayrollAnnualTaxIssueCode $code, string $field, string $message, ?string $reference = null): array
    {
        return ['code' => $code, 'severity' => PayrollAnnualTaxValidationSeverity::Blocking, 'field' => $field, 'reference' => $reference, 'message' => $message];
    }

    private function draft(PayrollAnnualTaxReconciliation $model, bool $existing): AnnualTaxDraftData
    {
        $taxpayer = $model->taxpayerSnapshotValue();
        $company = $model->companySnapshotValue();

        return new AnnualTaxDraftData(
            $model->id,
            $model->company_id,
            $model->employee_id,
            $model->tax_year,
            $model->jurisdiction,
            $model->currency,
            $model->reference,
            $model->version,
            $model->statusValue()->value,
            $this->taxpayerDraftShape($taxpayer),
            $this->companyDraftShape($company),
            $this->policyDraftShape($model->policySnapshotValue()),
            $existing,
        );
    }

    /** @return array<string, bool|int|string|null> */
    private function metadata(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \UnexpectedValueException('Annual Tax component metadata must be a JSON object.');
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && (is_bool($value) || is_int($value) || is_string($value) || $value === null)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $snapshot
     * @return array{employee_id:int,employee_code:string,display_name:string,tax_identifier:string|null,tax_residency:string|null,employment_scope:string}
     */
    private function taxpayerDraftShape(array $snapshot): array
    {
        return [
            'employee_id' => $this->arrayInt($snapshot, 'employee_id'),
            'employee_code' => $this->arrayString($snapshot, 'employee_code'),
            'display_name' => $this->arrayString($snapshot, 'display_name'),
            'tax_identifier' => $this->arrayNullableString($snapshot, 'tax_identifier'),
            'tax_residency' => $this->arrayNullableString($snapshot, 'tax_residency'),
            'employment_scope' => $this->arrayString($snapshot, 'employment_scope'),
        ];
    }

    /** @param array<string, mixed> $snapshot
     * @return array{company_id:int,company_code:string,legal_name:string,tax_identifier:string|null,address:string|null,jurisdiction:string}
     */
    private function companyDraftShape(array $snapshot): array
    {
        return [
            'company_id' => $this->arrayInt($snapshot, 'company_id'),
            'company_code' => $this->arrayString($snapshot, 'company_code'),
            'legal_name' => $this->arrayString($snapshot, 'legal_name'),
            'tax_identifier' => $this->arrayNullableString($snapshot, 'tax_identifier'),
            'address' => $this->arrayNullableString($snapshot, 'address'),
            'jurisdiction' => $this->arrayString($snapshot, 'jurisdiction'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, array<int, array<string, int|string|null>>|bool|int|string|null>|null
     */
    private function policyDraftShape(?array $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }
        $result = [];
        foreach ($snapshot as $key => $value) {
            if (is_bool($value) || is_int($value) || is_string($value) || $value === null) {
                $result[$key] = $value;

                continue;
            }
            if (! is_array($value)) {
                throw new \UnexpectedValueException("Annual Tax policy snapshot {$key} has an unsupported value.");
            }
            $rows = [];
            foreach ($value as $row) {
                if (! is_array($row)) {
                    throw new \UnexpectedValueException("Annual Tax policy snapshot {$key} must contain objects.");
                }
                $normalized = [];
                foreach ($row as $field => $item) {
                    if (is_string($field) && (is_int($item) || is_string($item) || $item === null)) {
                        $normalized[$field] = $item;
                    }
                }
                $rows[] = $normalized;
            }
            $result[$key] = $rows;
        }

        return $result;
    }

    /** @param array<string, mixed> $values */
    private function arrayInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (! is_int($value)) {
            throw new \UnexpectedValueException("Annual Tax snapshot {$key} must be an integer.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function arrayString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (! is_string($value)) {
            throw new \UnexpectedValueException("Annual Tax snapshot {$key} must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private function arrayNullableString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new \UnexpectedValueException("Annual Tax snapshot {$key} must be a nullable string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        return $this->n($row, $key) ?? '';
    }

    private function n(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : null;
    }
}
