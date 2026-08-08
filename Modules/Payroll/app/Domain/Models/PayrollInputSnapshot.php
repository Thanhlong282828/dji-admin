<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $company_id @property int $payroll_period_id @property int $snapshot_version */
final class PayrollInputSnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['generated_at' => 'immutable_datetime', 'locked_at' => 'immutable_datetime'];
    }
}
