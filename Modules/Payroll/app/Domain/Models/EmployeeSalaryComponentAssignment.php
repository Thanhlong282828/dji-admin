<?php

namespace Modules\Payroll\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable $effective_from @property int $version */
final class EmployeeSalaryComponentAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
