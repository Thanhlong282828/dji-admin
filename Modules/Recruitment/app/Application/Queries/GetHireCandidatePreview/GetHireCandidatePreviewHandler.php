<?php

namespace Modules\Recruitment\Application\Queries\GetHireCandidatePreview;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Recruitment\Application\DTOs\HireCandidatePreview;
use Modules\Recruitment\Domain\Services\CandidateOfferService;

final readonly class GetHireCandidatePreviewHandler
{
    public function __construct(private CandidateOfferService $offers) {}

    public function handle(GetHireCandidatePreviewQuery $query): HireCandidatePreview
    {
        $offer = $this->offers->detail($query->companyId, $query->offerId);
        $application = $offer->application;
        $candidate = $application?->candidate;
        $unit = $offer->organizationUnit;
        $position = $offer->jobPosition;
        if ($application === null || $candidate === null || $unit === null || $position === null) {
            throw (new ModelNotFoundException)->setModel($offer::class, [$offer->id]);
        }

        return new HireCandidatePreview(
            (int) $offer->id,
            $offer->version,
            (int) $application->id,
            $application->version,
            $candidate->full_name,
            $candidate->email,
            (int) $offer->organization_unit_id,
            $unit->name,
            (int) $offer->job_position_id,
            $position->title,
            $offer->employment_type,
            $offer->start_date,
            $offer->status->value,
            $offer->reservation_status,
            $query->canViewSalary ? $offer->base_salary : null,
            $query->canViewSalary ? $offer->currency : null,
            $offer->hired_employee_id !== null,
            $offer->hired_employee_id,
        );
    }
}
