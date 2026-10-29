<?php

namespace Modules\Leave\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $company_id @property int $leave_type_id @property int $annual_entitlement_minutes @property bool $negative_balance_allowed @property bool $requires_attachment */
final class LeavePolicy extends Model
{
    protected $fillable = [
    ];

    protected function casts(): array
    {
        return ['negative_balance_allowed' => 'boolean', 'requires_attachment' => 'boolean', 'carry_forward_rule' => 'array', 'approval_workflow' => 'array', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
