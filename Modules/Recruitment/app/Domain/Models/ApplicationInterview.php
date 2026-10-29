<?php

namespace Modules\Recruitment\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Recruitment\Domain\Enums\InterviewStatus;
use Modules\Recruitment\Domain\Enums\InterviewType;

/**
 * @property int $id
 * @property int $company_id
 * @property int $application_id
 * @property int $version
 * @property InterviewStatus $status
 * @property InterviewType $interview_type
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property string|null $location
 * @property string|null $meeting_url
 * @property int $round_number
 * @property int|null $scheduled_by
 */
final class ApplicationInterview extends Model
{
    protected $fillable = [
        'application_id',
        'company_id',
        'ends_at',
        'instructions',
        'interview_type',
        'location',
        'meeting_url',
        'round_number',
        'scheduled_by',
        'starts_at',
        'status',
        'timezone',
        'title',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'interview_type' => InterviewType::class,
            'status' => InterviewStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return HasMany<ApplicationInterviewer, $this> */
    public function interviewers(): HasMany
    {
        return $this->hasMany(ApplicationInterviewer::class);
    }

    /** @return HasMany<InterviewEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class);
    }
}
