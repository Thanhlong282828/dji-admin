<?php

namespace Modules\Onboarding\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OnboardingProvisioningItem extends Model
{
    protected $fillable = [
        'code',
        'company_id',
        'due_at',
        'is_required',
        'metadata',
        'provider',
        'provider_status',
        'status',
        'title',
        'type',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'due_at' => 'immutable_datetime', 'requested_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<OnboardingCase,$this> */
    public function onboardingCase(): BelongsTo
    {
        return $this->belongsTo(OnboardingCase::class);
    }
}
