<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;

/**
 * @property int $id
 * @property int $company_id
 * @property int $payroll_period_id
 * @property int $payroll_input_snapshot_id
 * @property string $currency_code
 * @property PayrollRunStatus $status
 * @property int $version
 * @property int $calculation_version
 * @property int|null $created_by
 * @property int|null $submitted_by
 */
final class PayrollRun extends Model
{
    protected $fillable = [
        'code',
        'calculated_at',
        'calculation_version',
        'company_id',
        'created_by',
        'currency_code',
        'deduction_total',
        'deduction_total_minor',
        'employee_count',
        'employer_contribution_total_minor',
        'formula_version',
        'finalized_at',
        'finalized_by',
        'gross_total',
        'gross_total_minor',
        'idempotency_key',
        'insurance_employee_total_minor',
        'insurance_employer_total_minor',
        'net_after_personal_income_tax_total_minor',
        'net_total',
        'net_total_minor',
        'payroll_input_snapshot_id',
        'payroll_period_id',
        'personal_income_tax_total_minor',
        'period_end',
        'period_start',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'run_number',
        'status',
        'submitted_at',
        'submitted_by',
        'version',
    ];

    protected function casts(): array
    {
        return ['status' => PayrollRunStatus::class, 'version' => 'integer', 'calculation_version' => 'integer', 'period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'calculated_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime', 'finalized_at' => 'immutable_datetime', 'formula_version' => 'array'];
    }
}
