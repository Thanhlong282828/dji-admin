<?php

namespace Modules\Recruitment\Application\Queries\GetInterviewExport;

use Generator;
use Modules\Recruitment\Application\DTOs\RecruitmentExportData;
use Modules\Recruitment\Domain\Models\ApplicationInterview;

final class GetInterviewExportHandler
{
    public function handle(GetInterviewExportQuery $query): RecruitmentExportData
    {
        return new RecruitmentExportData(
            'schema-interviews-'.now()->format('Ymd-His').'.csv',
            ['Interview ID', 'Candidate', 'Position', 'Round', 'Type', 'Status', 'Starts At', 'Ends At', 'Timezone', 'Panel'],
            $this->rows($query->companyId),
        );
    }

    /** @return Generator<int, array<int, bool|float|int|string|null>, mixed, void> */
    private function rows(int $companyId): Generator
    {
        foreach (ApplicationInterview::query()->with(['application.candidate:id,full_name', 'application.requisition.jobPosition:id,title', 'interviewers.user:id,name'])->where('company_id', $companyId)->orderBy('id')->lazyById(200) as $item) {
            yield [
                $this->cell($item->id),
                $this->candidateName($item),
                $this->positionTitle($item),
                $this->cell($item->round_number),
                $this->cell($item->interview_type->value),
                $this->cell($item->status->value),
                $this->cell($item->starts_at->toIso8601String()),
                $this->cell($item->ends_at->toIso8601String()),
                $this->cell($item->timezone),
                $this->cell($item->interviewers->pluck('user.name')->join('; ')),
            ];
        }
    }

    private function cell(mixed $value): bool|float|int|string|null
    {
        return is_bool($value) || is_float($value) || is_int($value) || is_string($value) || $value === null
            ? $value
            : null;
    }

    private function candidateName(ApplicationInterview $interview): string
    {
        $application = $interview->application;
        if ($application === null || $application->candidate === null) {
            return '';
        }

        return $application->candidate->full_name;
    }

    private function positionTitle(ApplicationInterview $interview): string
    {
        $application = $interview->application;
        if ($application === null || $application->requisition === null || $application->requisition->jobPosition === null) {
            return '';
        }

        return $application->requisition->jobPosition->title;
    }
}
