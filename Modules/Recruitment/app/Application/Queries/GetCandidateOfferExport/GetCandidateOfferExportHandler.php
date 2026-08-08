<?php

namespace Modules\Recruitment\Application\Queries\GetCandidateOfferExport;

use Generator;
use Modules\Recruitment\Application\DTOs\RecruitmentExportData;
use Modules\Recruitment\Domain\Models\CandidateOffer;

final class GetCandidateOfferExportHandler
{
    public function handle(GetCandidateOfferExportQuery $query): RecruitmentExportData
    {
        $headings = ['Offer Number', 'Candidate', 'Position', 'Employment Type', 'Currency', 'Start Date', 'Expires At', 'Status', 'Reservation'];
        if ($query->includeSalary) {
            $headings[] = 'Base Salary';
        }

        return new RecruitmentExportData('schema-candidate-offers-'.now()->format('Ymd-His').'.csv', $headings, $this->rows($query->companyId, $query->includeSalary));
    }

    /** @return Generator<int, array<int, bool|float|int|string|null>, mixed, void> */
    private function rows(int $companyId, bool $includeSalary): Generator
    {
        foreach (CandidateOffer::query()->with(['application.candidate:id,full_name', 'jobPosition:id,title'])->where('company_id', $companyId)->orderBy('id')->lazyById(200) as $offer) {
            $row = [
                $offer->offer_number,
                $offer->application?->candidate->full_name ?? '',
                $this->positionTitle($offer),
                $offer->employment_type,
                $offer->currency,
                $offer->start_date->toDateString(),
                $offer->expires_at?->toIso8601String(),
                $offer->status->value,
                $offer->reservation_status,
            ];
            if ($includeSalary) {
                $row[] = $offer->base_salary;
            }
            yield $row;
        }
    }

    private function positionTitle(CandidateOffer $offer): string
    {
        $position = $offer->jobPosition;
        if ($position === null) {
            return '';
        }

        return $position->title;
    }
}
