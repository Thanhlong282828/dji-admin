<?php

namespace Modules\Recruitment\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;

/**
 * @property int $id
 * @property int $company_id
 * @property int $application_id
 * @property int $job_position_id
 * @property int $organization_unit_id
 * @property int|null $hired_employee_id
 * @property int $version
 * @property int|null $submitted_by
 * @property int|null $sent_by
 * @property string $reservation_status
 * @property string|null $document_storage_key
 * @property string|null $document_mime_type
 * @property string|null $scan_status
 * @property string $offer_number
 * @property string $employment_type
 * @property string $currency
 * @property string $base_salary
 * @property CarbonImmutable $start_date
 * @property CandidateOfferStatus $status
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $accepted_at
 */
final class CandidateOffer extends Model
{
    protected $fillable = [
        'accepted_at',
        'application_id',
        'base_salary',
        'company_id',
        'currency',
        'employment_type',
        'expires_at',
        'hired_at',
        'hired_employee_id',
        'job_position_id',
        'offer_number',
        'organization_unit_id',
        'probation_end_date',
        'reservation_status',
        'reserved_at',
        'start_date',
        'status',
        'submitted_by',
        'terms',
        'version',
    ];

    protected $hidden = ['base_salary', 'document_storage_key'];

    protected function casts(): array
    {
        return ['status' => CandidateOfferStatus::class, 'base_salary' => 'decimal:2', 'start_date' => 'immutable_date', 'probation_end_date' => 'immutable_date', 'expires_at' => 'immutable_datetime', 'terms' => 'array', 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'declined_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime', 'reserved_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    /** @return BelongsTo<Application,$this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<JobPosition,$this> */
    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    /** @return BelongsTo<OrganizationUnit,$this> */
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }
}
