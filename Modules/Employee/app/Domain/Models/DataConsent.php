<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DataConsent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'decided_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
