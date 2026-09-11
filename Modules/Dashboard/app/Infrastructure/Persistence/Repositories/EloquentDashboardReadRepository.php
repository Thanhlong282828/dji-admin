<?php

namespace Modules\Dashboard\Infrastructure\Persistence\Repositories;

use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Modules\Dashboard\Application\DTOs\DashboardViewModel;
use Modules\Dashboard\Domain\Repositories\DashboardReadRepositoryInterface;
use Spatie\Activitylog\Models\Activity;

final class EloquentDashboardReadRepository implements DashboardReadRepositoryInterface
{
    public function overview(?int $companyId): DashboardViewModel
    {
        $employees = DB::table('employees')->when($companyId, fn ($query) => $query->where('company_id', $companyId))->whereNull('deleted_at');
        $rawStatuses = (clone $employees)->select('employment_status', DB::raw('COUNT(*) as aggregate'))->groupBy('employment_status')->pluck('aggregate', 'employment_status');
        $statuses = [];
        foreach ($rawStatuses as $status => $count) {
            if (is_string($status) && is_numeric($count)) {
                $statuses[$status] = (int) $count;
            }
        }

        $recentActivities = [];
        $activityQuery = Activity::query();
        if ($companyId !== null) {
            $activityQuery->where('properties->company_id', $companyId);
        }
        foreach ($activityQuery->latest()->limit(8)->get() as $activity) {
            $recentActivities[] = ['description' => $activity->description, 'subject_type' => class_basename((string) $activity->subject_type), 'created_at' => $activity->created_at?->toAtomString()];
        }

        $leave = DB::table('leave_requests')->when($companyId, fn ($query) => $query->where('company_id', $companyId));
        $recruitment = DB::table('recruitment_requisitions as requisition')->join('job_positions as position', 'position.id', '=', 'requisition.job_position_id')->when($companyId, fn ($query) => $query->where('position.company_id', $companyId));
        $articles = Article::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        return new DashboardViewModel((clone $employees)->count(), (clone $employees)->where('employment_status', 'active')->count(), (clone $leave)->where('status', 'pending')->count(), (clone $recruitment)->where('requisition.status', 'open')->count(), (clone $articles)->count(), (clone $articles)->where('status', 'published')->count(), (clone $articles)->where('status', 'draft')->count(), (clone $articles)->where('featured', true)->count(), $statuses, $recentActivities);
    }
}
