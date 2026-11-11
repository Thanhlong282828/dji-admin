<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeDocument extends Model
{
    protected $fillable = [
        'access_policy',
        'checksum',
        'employee_id',
        'mime_type',
        'name',
        'scan_status',
        'size_bytes',
        'storage_key',
        'type',
        'version',
    ];

    protected $hidden = ['storage_key', 'access_policy'];

    protected function casts(): array
    {
        return ['access_policy' => 'array', 'expires_at' => 'date', 'signed_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
