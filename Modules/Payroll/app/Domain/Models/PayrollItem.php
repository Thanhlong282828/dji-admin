<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class PayrollItem extends Model
{
    protected $fillable = [
    ];

    protected function casts(): array
    {
        return ['breakdown' => 'array', 'calculated_at' => 'immutable_datetime'];
    }
}
