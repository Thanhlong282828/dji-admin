<?php

namespace Modules\Recruitment\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Employee\Domain\Models\Employee;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Recruitment\Domain\Enums\RecruitmentRequisitionStatus;

/**
 * @property RecruitmentRequisitionStatus $status
 * @property int $id
 * @property int $job_position_id
 * @property int $company_id
 * @property int $vacancies
 * @property int $filled_hires
 * @property int $version
 */
final class RecruitmentRequisition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => RecruitmentRequisitionStatus::class, 'budget' => 'decimal:2', 'approval_context' => 'array', 'target_date' => 'date'];
    }

    /** @return BelongsTo<JobPosition, $this> */
    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
