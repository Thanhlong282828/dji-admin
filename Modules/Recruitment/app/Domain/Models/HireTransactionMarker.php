<?php

namespace Modules\Recruitment\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int $offer_id
 * @property int|null $employee_id
 * @property int|null $onboarding_case_id
 * @property string $idempotency_key
 * @property string $status
 */
final class HireTransactionMarker extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }
}
