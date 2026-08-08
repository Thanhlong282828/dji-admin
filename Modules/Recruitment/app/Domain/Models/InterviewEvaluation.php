<?php

namespace Modules\Recruitment\Domain\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $application_interview_id
 * @property int $interviewer_user_id
 * @property int $version
 * @property CarbonImmutable|null $submitted_at
 * @property string $overall_score
 * @property string $recommendation
 * @property array<array-key, mixed>|null $scorecard
 * @property string|null $strengths
 * @property string|null $concerns
 */
final class InterviewEvaluation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scorecard' => 'array', 'overall_score' => 'decimal:2', 'submitted_at' => 'immutable_datetime', 'reopened_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isDraft(): bool
    {
        return ! $this->isSubmitted();
    }

    /** @return BelongsTo<ApplicationInterview,$this> */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(ApplicationInterview::class, 'application_interview_id');
    }

    /** @return BelongsTo<User,$this> */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }
}
