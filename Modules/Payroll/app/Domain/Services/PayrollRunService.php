<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Calculation\PayrollCalculationEngine;
use Modules\Payroll\Domain\Calculation\PayrollComponentInput;
use Modules\Payroll\Domain\Enums\PayrollCalculationType;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Enums\SalaryComponentCategory;
use Modules\Payroll\Domain\Events\PayrollRunChanged;
use Modules\Payroll\Domain\Models\PayrollRun;

final readonly class PayrollRunService
{
    public function __construct(private PayrollRunStateMachine $states, private PayrollCalculationEngine $engine, private PayrollInsuranceIntegrator $insuranceIntegrator, private PayrollTaxIntegrator $taxIntegrator) {}

    public function create(int $company, int $periodId, int $snapshotId, string $runNumber, string $currency, string $key, int $actor): PayrollRun
    {
        return DB::transaction(function () use ($company, $periodId, $snapshotId, $runNumber, $currency, $key, $actor): PayrollRun {
            $period = DB::table('payroll_periods')->where('company_id', $company)->lockForUpdate()->find($periodId);
            if (! is_object($period)) {
                abort(404);
            }
            $periodStatus = $this->text($period, 'status');
            $periodStart = $this->text($period, 'period_start');
            $periodEnd = $this->text($period, 'period_end');
            if (! in_array($periodStatus, ['review', 'locked'], true)) {
                throw ValidationException::withMessages(['period' => 'Payroll Period phải ở review/locked.']);
            }
            $snapshot = DB::table('payroll_input_snapshots')->where('company_id', $company)->where('payroll_period_id', $periodId)->where('id', $snapshotId)->lockForUpdate()->first();
            if (! $snapshot) {
                throw ValidationException::withMessages(['snapshot' => 'Payroll Input Snapshot không hợp lệ.']);
            }
            $existing = PayrollRun::query()->where('company_id', $company)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            if (PayrollRun::query()->where('company_id', $company)->where('payroll_period_id', $periodId)->exists()) {
                throw ValidationException::withMessages(['period' => 'Period đã có Payroll Run.']);
            }
            $run = new PayrollRun(['company_id' => $company, 'payroll_period_id' => $periodId, 'payroll_input_snapshot_id' => $snapshotId, 'run_number' => $runNumber, 'code' => $runNumber, 'period_start' => $periodStart, 'period_end' => $periodEnd, 'status' => 'draft', 'currency_code' => $currency, 'formula_version' => ['engine' => 'foundation-v1', 'money' => 'integer-minor'], 'created_by' => $actor, 'idempotency_key' => $key]);
            $run->setAttribute('uuid', (string) Str::uuid());
            $run->save();
            $this->history($run, null, 'draft', 'created', $actor, 0, 1, $key, null);
            activity('payroll-run')->performedOn($run)->causedBy($actor)->event('created')->withProperties(['company_id' => $company, 'run_id' => $run->id])->log('Payroll Run created');
            event(new PayrollRunChanged($company, $run->id, $periodId, 'created', 'draft', 1, 0));

            return $run;
        }, 3);
    }

    public function calculate(int $company, int $id, int $expected, string $key, int $actor): PayrollRun
    {
        return DB::transaction(function () use ($company, $id, $expected, $key, $actor): PayrollRun {
            $run = $this->run($company, $id);
            $this->version($run, $expected);
            $from = $run->status;
            if (! in_array($from, [PayrollRunStatus::Draft, PayrollRunStatus::Calculated, PayrollRunStatus::ValidationFailed, PayrollRunStatus::Failed, PayrollRunStatus::Rejected], true)) {
                throw ValidationException::withMessages(['status' => 'Run không cho phép calculate/recalculate.']);
            }
            if (DB::table('payroll_run_histories')->where('company_id', $company)->where('payroll_run_id', $id)->where('action', 'calculated')->where('idempotency_key', $key)->exists()) {
                return $run;
            }
            $this->states->assert($from, PayrollRunStatus::Calculating);
            $run->update(['status' => 'calculating']);
            $inputs = DB::table('payroll_employee_inputs as i')->join('employees as e', 'e.id', '=', 'i.employee_id')->where('i.company_id', $company)->where('i.payroll_input_snapshot_id', $run->payroll_input_snapshot_id)->select('i.*', 'e.employee_code', 'e.full_name')->orderBy('i.employee_id')->get();
            if ($inputs->isEmpty()) {
                throw ValidationException::withMessages(['snapshot' => 'Snapshot không có Employee input.']);
            }
            $employeeIds = [];
            foreach ($inputs as $employeeInput) {
                $employeeIds[] = $this->integer($employeeInput, 'employee_id');
            }
            $assignments = DB::table('employee_salary_component_assignments as a')->join('salary_components as c', 'c.id', '=', 'a.salary_component_id')->where('a.company_id', $company)->whereIn('a.employee_id', $employeeIds)->where('a.status', 'active')->where('c.status', 'active')->whereDate('a.effective_from', '<=', $run->period_end)->where(fn (Builder $q) => $q->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $run->period_start))->select('a.*', 'c.code', 'c.name', 'c.category', 'c.calculation_type', 'c.system_key', 'c.insurance_status', 'c.taxable_status', 'c.affects_gross', 'c.affects_net', 'c.display_order')->orderBy('c.display_order')->get()->groupBy('employee_id');
            $insuranceContext = $this->insuranceIntegrator->context($company, $employeeIds, (string) $run->period_end);
            $taxContext = $this->taxIntegrator->context($company, $employeeIds, (string) $run->period_end);
            DB::table('payroll_validation_issues')->where('payroll_run_id', $id)->delete();
            DB::table('payroll_item_components')->where('payroll_run_id', $id)->delete();
            DB::table('payroll_items')->where('payroll_run_id', $id)->delete();
            $gross = $deduction = $employer = $net = $insuranceEmployeeRun = $insuranceEmployerRun = $taxRun = $netAfterTaxRun = 0;
            $invalid = false;
            $calculationVersion = $run->calculation_version + 1;
            foreach ($inputs as $employeeInput) {
                $componentInputs = [];
                $insuranceStatuses = [];
                foreach ($assignments->get($this->integer($employeeInput, 'employee_id'), collect()) as $a) {
                    $currency = $this->nullableText($a, 'currency_code') ?? $run->currency_code;
                    if ($currency !== $run->currency_code) {
                        $componentInputs = [];
                        $invalid = true;
                        break;
                    }$componentId = $this->integer($a, 'salary_component_id');
                    $quantity = $this->inputQuantityBasisPoints($this->nullableText($a, 'system_key'), $employeeInput, $this->nullableInteger($a, 'quantity_basis_points'));
                    $componentInputs[] = new PayrollComponentInput($componentId, $this->text($a, 'code'), $this->text($a, 'name'), SalaryComponentCategory::from($this->text($a, 'category')), PayrollCalculationType::from($this->text($a, 'calculation_type')), $currency, $this->nullableInteger($a, 'amount_minor'), $this->nullableInteger($a, 'percentage_basis_points'), $quantity, $this->nullableInteger($a, 'rate_minor'), $this->integer($a, 'affects_gross') === 1, $this->integer($a, 'affects_net') === 1);
                    $insuranceStatuses[$componentId] = $this->text($a, 'insurance_status');
                }
                $result = $this->engine->calculate($componentInputs);
                $insurance = $this->insuranceIntegrator->calculate($insuranceContext, $this->integer($employeeInput, 'employee_id'), $result, $insuranceStatuses, (string) $run->currency_code);
                $tax = $this->taxIntegrator->calculate($taxContext, $this->integer($employeeInput, 'employee_id'), $result, $insurance->employeeTotalMinor, (string) $run->currency_code);
                $itemDeduction = $result->deductionMinor + $insurance->employeeTotalMinor;
                $itemEmployer = $result->employerContributionMinor + $insurance->employerTotalMinor;
                $itemNet = $result->netMinor - $insurance->employeeTotalMinor;
                $itemNetAfterTax = $itemNet - $tax->withholdingTaxMinor;
                $itemValid = $result->valid() && $insurance->valid() && $tax->issues === [];
                $itemId = (int) DB::table('payroll_items')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'payroll_run_id' => $id, 'employee_id' => $this->integer($employeeInput, 'employee_id'), 'gross_salary' => $result->grossMinor, 'insurance' => $insurance->employeeTotalMinor, 'personal_income_tax' => $tax->withholdingTaxMinor, 'deductions' => $itemDeduction, 'net_salary' => $itemNetAfterTax, 'breakdown' => '[]', 'calculation_version' => $calculationVersion, 'employee_number_snapshot' => $this->text($employeeInput, 'employee_code'), 'employee_name_snapshot' => $this->text($employeeInput, 'full_name'), 'currency_code' => $run->currency_code, 'scheduled_minutes' => $this->integer($employeeInput, 'scheduled_minutes'), 'worked_minutes' => $this->integer($employeeInput, 'worked_minutes'), 'paid_leave_minutes' => $this->integer($employeeInput, 'paid_leave_minutes'), 'unpaid_leave_minutes' => $this->integer($employeeInput, 'unpaid_leave_minutes'), 'approved_overtime_minutes' => $this->integer($employeeInput, 'approved_overtime_minutes'), 'weekday_overtime_minutes' => $this->integer($employeeInput, 'weekday_overtime_minutes'), 'weekend_overtime_minutes' => $this->integer($employeeInput, 'weekend_overtime_minutes'), 'holiday_overtime_minutes' => $this->integer($employeeInput, 'holiday_overtime_minutes'), 'insurance_policy_id' => $insurance->policyId, 'insurance_policy_version' => $insurance->policyVersion, 'tax_policy_id' => $tax->policyId, 'tax_policy_version' => $tax->policyVersion, 'taxable_income_before_floor_minor' => $tax->taxableIncomeBeforeFloorMinor, 'taxable_income_minor' => $tax->taxableIncomeMinor, 'personal_income_tax_minor' => $tax->withholdingTaxMinor, 'net_before_personal_income_tax_minor' => $itemNet, 'net_after_personal_income_tax_minor' => $itemNetAfterTax, 'insurance_contribution_base_minor' => $insurance->baseMinor, 'insurance_employee_total_minor' => $insurance->employeeTotalMinor, 'insurance_employer_total_minor' => $insurance->employerTotalMinor, 'gross_amount_minor' => $result->grossMinor, 'deduction_amount_minor' => $itemDeduction, 'employer_contribution_amount_minor' => $itemEmployer, 'net_amount_minor' => $itemNetAfterTax, 'calculation_status' => $itemValid ? 'calculated' : 'validation_failed', 'validation_status' => $itemValid ? 'valid' : 'error', 'calculated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($result->components as $line) {
                    DB::table('payroll_item_components')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'salary_component_id' => $line->input->componentId, 'component_code_snapshot' => $line->input->code, 'component_name_snapshot' => $line->input->name, 'category' => $line->input->category->value, 'calculation_type' => $line->input->type->value, 'source_type' => 'salary_component_assignment', 'amount_minor' => $line->amountMinor, 'currency_code' => $run->currency_code, 'calculation_order' => $line->order, 'created_at' => now(), 'updated_at' => now()]);
                }
                $insuranceOrder = count($result->components) + 1000;
                foreach ([...$insurance->employeeLines, ...$insurance->employerLines] as $line) {
                    DB::table('payroll_item_components')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'salary_component_id' => null, 'component_code_snapshot' => 'INSURANCE_'.strtoupper($line->party).'_'.strtoupper($line->type->value), 'component_name_snapshot' => ucfirst($line->party).' '.str_replace('_', ' ', $line->type->value).' insurance', 'category' => $line->party === 'employee' ? 'deduction' : 'employer_contribution', 'calculation_type' => 'percentage', 'source_type' => 'insurance_policy_rate', 'source_id' => $line->policyId, 'percentage_basis_points' => $line->rateBasisPoints, 'base_amount_minor' => $line->baseMinor, 'amount_minor' => $line->amountMinor, 'currency_code' => $run->currency_code, 'calculation_order' => $insuranceOrder++, 'calculation_metadata' => json_encode(['party' => $line->party, 'insurance_type' => $line->type->value, 'policy_id' => $line->policyId, 'policy_version' => $line->policyVersion, 'policy_effective_from' => $line->policyEffectiveFrom, 'calculation_version' => $calculationVersion], JSON_THROW_ON_ERROR), 'version' => $calculationVersion, 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($tax->policyId !== null) {
                    $taxOrder = $insuranceOrder + 1000;
                    DB::table('payroll_item_components')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'salary_component_id' => null, 'component_code_snapshot' => 'TAXABLE_INCOME', 'component_name_snapshot' => 'Taxable income', 'category' => 'informational', 'calculation_type' => 'fixed', 'source_type' => 'tax_policy', 'source_id' => $tax->policyId, 'base_amount_minor' => max(0, $tax->taxableIncomeBeforeFloorMinor), 'amount_minor' => $tax->taxableIncomeMinor, 'currency_code' => $run->currency_code, 'calculation_order' => $taxOrder++, 'calculation_metadata' => json_encode(['policy_id' => $tax->policyId, 'policy_version' => $tax->policyVersion, 'calculation_version' => $calculationVersion, 'before_floor_minor' => $tax->taxableIncomeBeforeFloorMinor], JSON_THROW_ON_ERROR), 'version' => $calculationVersion, 'created_at' => now(), 'updated_at' => now()]);
                    foreach ([['TAX_DEDUCTION_INSURANCE', 'Employee insurance tax deduction', $tax->employeeInsuranceDeductionMinor], ['TAX_DEDUCTION_PERSONAL', 'Personal tax deduction', $tax->personalDeductionMinor], ['TAX_DEDUCTION_DEPENDENT', 'Dependent tax deduction', $tax->dependentDeductionMinor], ['TAX_DEDUCTION_OTHER', 'Other configured tax deduction', $tax->otherDeductionMinor]] as [$code, $name, $amount]) {
                        DB::table('payroll_item_components')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'salary_component_id' => null, 'component_code_snapshot' => $code, 'component_name_snapshot' => $name, 'category' => 'informational', 'calculation_type' => 'fixed', 'source_type' => 'tax_policy_deduction', 'source_id' => $tax->policyId, 'amount_minor' => $amount, 'currency_code' => $run->currency_code, 'calculation_order' => $taxOrder++, 'calculation_metadata' => json_encode(['policy_id' => $tax->policyId, 'policy_version' => $tax->policyVersion, 'calculation_version' => $calculationVersion], JSON_THROW_ON_ERROR), 'version' => $calculationVersion, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    foreach ($tax->bracketLines as $line) {
                        DB::table('payroll_item_components')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'salary_component_id' => null, 'component_code_snapshot' => 'PIT_BRACKET_'.$line->sequence, 'component_name_snapshot' => 'Personal income tax bracket '.$line->sequence, 'category' => 'deduction', 'calculation_type' => 'percentage', 'source_type' => 'tax_policy_bracket', 'source_id' => $tax->policyId, 'percentage_basis_points' => $line->rateBasisPoints, 'base_amount_minor' => $line->taxablePortionMinor, 'amount_minor' => $line->amountMinor, 'currency_code' => $run->currency_code, 'calculation_order' => $taxOrder++, 'calculation_metadata' => json_encode(['policy_id' => $tax->policyId, 'policy_version' => $tax->policyVersion, 'lower_bound_minor' => $line->lowerBoundMinor, 'upper_bound_minor' => $line->upperBoundMinor, 'calculation_version' => $calculationVersion], JSON_THROW_ON_ERROR), 'version' => $calculationVersion, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
                foreach ($tax->issues as $issue) {
                    $invalid = true;
                    DB::table('payroll_validation_issues')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'severity' => 'error', 'code' => $issue, 'safe_message' => 'Tax configuration is incomplete for this payroll calculation.', 'metadata' => json_encode(['calculation_version' => $calculationVersion], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($insurance->issues as $issue) {
                    $invalid = true;
                    DB::table('payroll_validation_issues')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'severity' => 'error', 'code' => $issue, 'safe_message' => 'Insurance configuration is incomplete for this payroll calculation.', 'metadata' => json_encode(['calculation_version' => $calculationVersion], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($result->issues as $issue) {
                    $invalid = true;
                    DB::table('payroll_validation_issues')->insert(['company_id' => $company, 'payroll_run_id' => $id, 'payroll_item_id' => $itemId, 'severity' => 'error', 'code' => 'calculation_configuration', 'safe_message' => $issue, 'created_at' => now(), 'updated_at' => now()]);
                }$gross += $result->grossMinor;
                $deduction += $itemDeduction;
                $employer += $itemEmployer;
                $net += $itemNet;
                $insuranceEmployeeRun += $insurance->employeeTotalMinor;
                $insuranceEmployerRun += $insurance->employerTotalMinor;
                $taxRun += $tax->withholdingTaxMinor;
                $netAfterTaxRun += $itemNetAfterTax;
            }
            $to = $invalid ? PayrollRunStatus::ValidationFailed : PayrollRunStatus::Calculated;
            $before = $expected;
            $after = $expected + 1;
            $run->update(['status' => $to->value, 'calculation_version' => $calculationVersion, 'employee_count' => $inputs->count(), 'gross_total_minor' => $gross, 'deduction_total_minor' => $deduction, 'employer_contribution_total_minor' => $employer, 'insurance_employee_total_minor' => $insuranceEmployeeRun, 'insurance_employer_total_minor' => $insuranceEmployerRun, 'personal_income_tax_total_minor' => $taxRun, 'net_total_minor' => $netAfterTaxRun, 'net_after_personal_income_tax_total_minor' => $netAfterTaxRun, 'gross_total' => $gross, 'deduction_total' => $deduction, 'net_total' => $netAfterTaxRun, 'calculated_at' => now(), 'version' => $after]);
            $this->history($run, $from->value, $to->value, 'calculated', $actor, $before, $after, $key, null);
            activity('payroll-run')->performedOn($run)->causedBy($actor)->event('calculated')->withProperties(['company_id' => $company, 'run_id' => $id, 'calculation_version' => $calculationVersion])->log('Payroll Run calculated');
            event(new PayrollRunChanged($company, $id, (int) $run->payroll_period_id, 'calculated', $to->value, $after, $calculationVersion));

            return $run->refresh();
        }, 3);
    }

    public function transition(int $company, int $id, PayrollRunStatus $to, int $expected, string $key, int $actor, ?string $reason = null): PayrollRun
    {
        return DB::transaction(function () use ($company, $id, $to, $expected, $key, $actor, $reason): PayrollRun {
            $run = $this->run($company, $id);
            $this->version($run, $expected);
            $from = $run->status;
            $this->states->assert($from, $to);
            if ($to === PayrollRunStatus::Approved && in_array($actor, [(int) $run->created_by, (int) $run->submitted_by], true)) {
                throw ValidationException::withMessages(['approval' => 'Không được tự phê duyệt Payroll Run.']);
            }
            if (in_array($to, [PayrollRunStatus::Submitted, PayrollRunStatus::Finalized], true)) {
                $this->assertConsistent($run);
            }
            $after = $expected + 1;
            $data = ['status' => $to->value, 'version' => $after];
            $stamp = match ($to) {
                PayrollRunStatus::Submitted => 'submitted_at',PayrollRunStatus::Approved => 'approved_at',PayrollRunStatus::Rejected => 'rejected_at',PayrollRunStatus::Finalized => 'finalized_at',default => null
            };
            $actorColumn = match ($to) {
                PayrollRunStatus::Submitted => 'submitted_by',PayrollRunStatus::Approved => 'approved_by',PayrollRunStatus::Rejected => 'rejected_by',PayrollRunStatus::Finalized => 'finalized_by',default => null
            };
            if ($stamp) {
                $data[$stamp] = now();
            }if ($actorColumn) {
                $data[$actorColumn] = $actor;
            }$run->update($data);
            $this->history($run, $from->value, $to->value, $to->value, $actor, $expected, $after, $key, $reason);
            activity('payroll-run')->performedOn($run)->causedBy($actor)->event($to->value)->withProperties(['company_id' => $company, 'run_id' => $id])->log('Payroll Run '.$to->value);
            event(new PayrollRunChanged($company, $id, (int) $run->payroll_period_id, $to->value, $to->value, $after, $run->calculation_version));

            return $run->refresh();
        }, 3);
    }

    private function run(int $company, int $id): PayrollRun
    {
        return PayrollRun::query()->where('company_id', $company)->lockForUpdate()->findOrFail($id);
    }

    private function version(PayrollRun $run, int $expected): void
    {
        if ($run->version !== $expected) {
            throw ValidationException::withMessages(['version' => 'Payroll Run đã thay đổi.']);
        }
    }

    private function assertConsistent(PayrollRun $run): void
    {
        if (DB::table('payroll_validation_issues')->where('payroll_run_id', $run->id)->where('severity', 'error')->whereNull('resolved_at')->exists()) {
            throw ValidationException::withMessages(['validation' => 'Run còn blocking validation errors.']);
        }
        $items = DB::table('payroll_items')->where('company_id', $run->company_id)->where('payroll_run_id', $run->id)->where('calculation_version', $run->calculation_version);
        if ($run->employee_count <= 0 || $items->count() !== $run->employee_count) {
            throw ValidationException::withMessages(['items' => 'Payroll Items không khớp employee count của Run.']);
        }
        $gross = (int) (clone $items)->sum('gross_amount_minor');
        $deduction = (int) (clone $items)->sum('deduction_amount_minor');
        $employer = (int) (clone $items)->sum('employer_contribution_amount_minor');
        $insuranceEmployee = (int) (clone $items)->sum('insurance_employee_total_minor');
        $insuranceEmployer = (int) (clone $items)->sum('insurance_employer_total_minor');
        $net = (int) (clone $items)->sum('net_amount_minor');
        if ($gross !== $run->gross_total_minor || $deduction !== $run->deduction_total_minor || $employer !== $run->employer_contribution_total_minor || $insuranceEmployee !== $run->insurance_employee_total_minor || $insuranceEmployer !== $run->insurance_employer_total_minor || $net !== $run->net_total_minor) {
            throw ValidationException::withMessages(['totals' => 'Payroll Run totals không nhất quán với current calculation version.']);
        }
    }

    private function history(PayrollRun $run, ?string $from, string $to, string $action, int $actor, int $before, int $after, string $key, ?string $reason): void
    {
        DB::table('payroll_run_histories')->insert(['company_id' => $run->company_id, 'payroll_run_id' => $run->id, 'from_status' => $from, 'to_status' => $to, 'action' => $action, 'actor_id' => $actor, 'version_before' => $before, 'version_after' => $after, 'safe_reason' => $reason, 'idempotency_key' => $key, 'created_at' => now()]);
    }

    private function text(object $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }

    private function nullableText(object $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : null;
    }

    private function integer(object $row, string $property): int
    {
        return $this->nullableInteger($row, $property) ?? 0;
    }

    private function nullableInteger(object $row, string $property): ?int
    {
        $value = $row->{$property} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function inputQuantityBasisPoints(?string $systemKey, object $employeeInput, ?int $configured): ?int
    {
        $minutes = match ($systemKey) {
            'weekday_overtime_pay' => $this->integer($employeeInput, 'weekday_overtime_minutes'),
            'weekend_overtime_pay' => $this->integer($employeeInput, 'weekend_overtime_minutes'),
            'holiday_overtime_pay' => $this->integer($employeeInput, 'holiday_overtime_minutes'),
            'unpaid_leave_deduction' => $this->integer($employeeInput, 'unpaid_leave_minutes'),
            'late_time_deduction' => $this->integer($employeeInput, 'late_minutes'),
            default => null,
        };
        if ($minutes === null) {
            return $configured;
        }

        // QuantityRate uses basis points; one hour is represented by 10,000.
        return intdiv(($minutes * 10_000) + 30, 60);
    }
}
