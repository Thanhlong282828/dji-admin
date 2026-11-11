<?php

namespace Modules\Onboarding\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OnboardingChecklistItem extends Model
{
    protected $fillable = [
        'category',
        'code',
        'company_id',
        'description',
        'due_at',
        'is_required',
        'metadata',
        'sort_order',
        'status',
        'title',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'due_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'reopened_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<OnboardingCase,$this> */
    public function onboardingCase(): BelongsTo
    {
        return $this->belongsTo(OnboardingCase::class);
    }
}
