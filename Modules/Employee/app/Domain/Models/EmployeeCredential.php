<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employee\Domain\Enums\CredentialType;

final class EmployeeCredential extends Model
{
    protected $fillable = [
        'employee_id',
        'expires_at',
        'issued_at',
        'issuer',
        'name',
        'type',
    ];

    protected $hidden = ['credential_number', 'document_url', 'document_storage_key'];

    protected function casts(): array
    {
        return ['type' => CredentialType::class, 'issued_at' => 'date', 'expires_at' => 'date', 'archived_at' => 'datetime', 'attributes' => 'array'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
