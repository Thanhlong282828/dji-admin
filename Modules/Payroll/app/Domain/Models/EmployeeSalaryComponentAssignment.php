<?php

namespace Modules\Payroll\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable $effective_from @property int $version */
final class EmployeeSalaryComponentAssignment extends Model
{
    protected $fillable = [
        'amount_minor',
        'company_id',
        'created_by',
        'currency_code',
        'effective_from',
        'effective_to',
        'employee_id',
        'idempotency_key',
        'percentage_basis_points',
        'quantity_basis_points',
        'rate_minor',
        'salary_component_id',
        'source',
        'status',
        'updated_by',
        'version',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
