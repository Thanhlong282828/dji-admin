<?php

namespace Modules\Recruitment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Recruitment\Domain\Enums\ApplicationStage;

/**
 * @property int $id
 * @property int $recruitment_requisition_id
 * @property int $candidate_id
 * @property int|null $hired_employee_id
 * @property ApplicationStage $stage
 * @property int $version
 */
final class Application extends Model
{
    protected $fillable = [
        'candidate_id',
        'hired_at',
        'hired_employee_id',
        'hiring_reference',
        'recruitment_requisition_id',
        'stage',
        'stage_changed_at',
        'stage_reason',
        'version',
    ];

    protected function casts(): array
    {
        return ['stage' => ApplicationStage::class, 'score' => 'decimal:2', 'evaluations' => 'array', 'stage_changed_at' => 'datetime'];
    }

    /** @return BelongsTo<RecruitmentRequisition, $this> */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(RecruitmentRequisition::class, 'recruitment_requisition_id');
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** @return HasMany<ApplicationInterview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(ApplicationInterview::class);
    }

    /** @return HasMany<CandidateOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(CandidateOffer::class);
    }
}
