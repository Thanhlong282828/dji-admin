<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Enums\PayrollCalculationType;
use Modules\Payroll\Domain\Enums\SalaryComponentCategory;

final class SalaryComponent extends Model
{
    protected $fillable = [
        'affects_gross',
        'affects_net',
        'calculation_type',
        'category',
        'code',
        'company_id',
        'created_by',
        'currency_code',
        'default_percentage_basis_points',
        'default_value_minor',
        'description',
        'display_order',
        'effective_from',
        'effective_to',
        'insurance_status',
        'is_system',
        'name',
        'status',
        'system_key',
        'taxable_status',
        'updated_by',
        'value_type',
        'version',
    ];

    protected function casts(): array
    {
        return ['category' => SalaryComponentCategory::class, 'calculation_type' => PayrollCalculationType::class, 'affects_gross' => 'boolean', 'affects_net' => 'boolean', 'is_system' => 'boolean', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
