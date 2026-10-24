<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;

/** @property int $id @property int $company_id @property string $code @property int $version @property PayrollPeriodStatus $status @property \Carbon\CarbonImmutable $period_start @property \Carbon\CarbonImmutable $period_end */
final class PayrollPeriod extends Model
{
    protected $fillable = [
        'code',
        'company_id',
        'created_by',
        'cutoff_at',
        'idempotency_key',
        'name',
        'note',
        'payment_date',
        'period_end',
        'period_start',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'cutoff_at' => 'immutable_datetime', 'payment_date' => 'immutable_date', 'locked_at' => 'immutable_datetime', 'status' => PayrollPeriodStatus::class];
    }
}
