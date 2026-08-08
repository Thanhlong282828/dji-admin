<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Enums\PayrollCalculationType;
use Modules\Payroll\Domain\Enums\SalaryComponentCategory;

final class SalaryComponent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['category' => SalaryComponentCategory::class, 'calculation_type' => PayrollCalculationType::class, 'affects_gross' => 'boolean', 'affects_net' => 'boolean', 'is_system' => 'boolean', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
