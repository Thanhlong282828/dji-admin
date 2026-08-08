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
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => PayrollRunStatus::class, 'period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'calculated_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime', 'finalized_at' => 'immutable_datetime', 'formula_version' => 'array'];
    }
}
