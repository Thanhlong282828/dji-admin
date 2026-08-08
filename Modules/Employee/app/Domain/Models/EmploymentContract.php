<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employee\Domain\Enums\ContractStatus;
use Modules\Employee\Domain\Enums\ContractType;

/** @property int $id */
final class EmploymentContract extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['base_salary', 'signed_document_url', 'signed_storage_key'];

    protected function casts(): array
    {
        return ['type' => ContractType::class, 'status' => ContractStatus::class, 'starts_at' => 'date', 'ends_at' => 'date', 'base_salary' => 'decimal:2', 'terms' => 'array', 'signed_at' => 'datetime', 'terminated_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
