<?php

namespace Modules\Organization\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class EmployeeAssignment extends Model
{
    protected $table = 'employee_assignments';

    protected $fillable = [
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'allocation_percent' => 'decimal:2'];
    }
}
