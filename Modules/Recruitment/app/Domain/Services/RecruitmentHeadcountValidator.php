<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Organization\Domain\Models\JobPosition;

final class RecruitmentHeadcountValidator
{
    /**
     * @return array{approved: float, allocated: float, reserved: float, accepted: float, available: float}
     */
    public function capacity(int $companyId, int $positionId, ?int $exceptRequisitionId = null): array
    {
        $position = JobPosition::query()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->findOrFail($positionId);

        $allocated = (float) DB::table('employee_assignments')
            ->where('job_position_id', $positionId)
            ->whereDate('effective_from', '<=', today())
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', today()))
            ->sum(DB::raw('allocation_percent / 100'));

        $reservedQuery = DB::table('recruitment_requisitions')
            ->where('job_position_id', $positionId)
            ->whereIn('status', ['approval', 'approved', 'open', 'paused']);

        if ($exceptRequisitionId !== null) {
            $reservedQuery->where('id', '<>', $exceptRequisitionId);
        }

        $reserved = (float) $reservedQuery->sum(DB::raw('GREATEST(vacancies - filled_hires, 0)'));
        // Offers belonging to an active requisition are already represented by
        // that requisition's remaining vacancy reservation and must not be
        // subtracted twice.
        $accepted = (float) DB::table('candidate_offers as offers')
            ->join('applications as applications', 'applications.id', '=', 'offers.application_id')
            ->join('recruitment_requisitions as requisitions', 'requisitions.id', '=', 'applications.recruitment_requisition_id')
            ->where('offers.job_position_id', $positionId)
            ->where('offers.status', 'accepted')
            ->whereNotIn('requisitions.status', ['approval', 'approved', 'open', 'paused'])
            ->count();
        $approved = (float) $position->approved_headcount;

        return [
            'approved' => $approved,
            'allocated' => $allocated,
            'reserved' => $reserved,
            'accepted' => $accepted,
            'available' => max(0.0, $approved - $allocated - $reserved - $accepted),
        ];
    }

    public function assertAvailable(
        int $companyId,
        int $positionId,
        int $requested,
        bool $override = false,
        ?string $reason = null,
        ?int $exceptRequisitionId = null,
    ): void {
        $capacity = $this->capacity($companyId, $positionId, $exceptRequisitionId);

        if ($requested > $capacity['available'] && ! $override) {
            throw ValidationException::withMessages([
                'vacancies' => 'Yêu cầu vượt capacity còn lại: '.$capacity['available'].' FTE.',
            ]);
        }

        if ($requested > $capacity['available'] && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'override_reason' => 'Override headcount bắt buộc có lý do.',
            ]);
        }
    }
}
