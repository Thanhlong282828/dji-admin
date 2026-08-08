<?php

namespace Modules\Recruitment\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Recruitment\Domain\Models\Application;
use Modules\Recruitment\Domain\Models\Candidate;
use Modules\Recruitment\Domain\Models\RecruitmentRequisition;
use Modules\Recruitment\Domain\Repositories\RecruitmentReadRepositoryInterface;

final class EloquentRecruitmentReadRepository implements RecruitmentReadRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RecruitmentRequisition>
     */
    public function requisitions(int $companyId, array $filters): LengthAwarePaginator
    {
        $query = RecruitmentRequisition::query()
            ->with(['jobPosition.organizationUnit:id,name', 'requester:id,full_name'])
            ->withCount([
                'applications',
                'applications as hired_count' => static fn (Builder $query) => $query->where('stage', 'hired'),
            ])
            ->whereHas('jobPosition', static fn ($query) => $query->where('company_id', $companyId));

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Candidate>
     */
    public function candidates(int $companyId, array $filters): LengthAwarePaginator
    {
        $query = Candidate::query()
            ->where('company_id', $companyId)
            ->withCount('applications');

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $query->where(static fn ($query) => $query
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        $source = $filters['source'] ?? null;
        if (is_string($source) && $source !== '') {
            $query->where('source', $source);
        }

        return $query->paginate(15);
    }

    public function pipeline(int $companyId, ?int $requisitionId): array
    {
        $query = Application::query()
            ->with(['candidate:id,full_name,source', 'requisition.jobPosition:id,title,company_id'])
            ->whereHas('requisition.jobPosition', static fn ($query) => $query->where('company_id', $companyId));

        if ($requisitionId !== null) {
            $query->where('recruitment_requisition_id', $requisitionId);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->orderByDesc('stage_changed_at')->limit(300)->get()->toArray();
        $grouped = [];
        foreach ($rows as $row) {
            $stage = $row['stage'] ?? null;
            if (is_string($stage)) {
                $grouped[$stage][] = $row;
            }
        }

        return $grouped;
    }

    public function dashboard(int $companyId, array $filters): array
    {
        $req = DB::table('recruitment_requisitions as rr')->join('job_positions as jp', 'jp.id', '=', 'rr.job_position_id')->where('jp.company_id', $companyId);
        $apps = DB::table('applications as a')->join('recruitment_requisitions as rr', 'rr.id', '=', 'a.recruitment_requisition_id')->join('job_positions as jp', 'jp.id', '=', 'rr.job_position_id')->where('jp.company_id', $companyId);

        /** @var array<string, int> $stageCounts */
        $stageCounts = (clone $apps)
            ->select('a.stage', DB::raw('COUNT(*) total'))
            ->groupBy('a.stage')
            ->pluck('total', 'stage')
            ->map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0)
            ->all();

        return [
            'open_requisitions' => (clone $req)->where('rr.status', 'open')->count(),
            'pending_approval' => (clone $req)->where('rr.status', 'approval')->count(),
            'total_vacancies' => (int) (clone $req)->whereIn('rr.status', ['approval', 'open', 'paused'])->sum('rr.vacancies'),
            'applications' => (clone $apps)->count(),
            'screening' => (clone $apps)->where('a.stage', 'screening')->count(),
            'interview' => (clone $apps)->where('a.stage', 'interview')->count(),
            'offers' => (clone $apps)->where('a.stage', 'offer')->count(),
            'hired' => (clone $apps)->where('a.stage', 'hired')->count(),
            'rejected' => (clone $apps)->where('a.stage', 'rejected')->count(),
            'stage_counts' => $stageCounts,
        ];
    }
}
