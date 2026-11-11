<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class EmployeeManagerHistory extends Model
{
    public $timestamps = false;

    protected $table = 'employee_manager_history';

    protected $fillable = [
        'checksum',
        'company_id',
        'effective_from',
        'employee_id',
        'manager_id',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'effective_to' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }
}
