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
        $statuses = (clone $employees)->select('employment_status', DB::raw('COUNT(*) as aggregate'))->groupBy('employment_status')->pluck('aggregate', 'employment_status')->map(fn (mixed $count): int => (int) $count)->all();

        return new DashboardViewModel((clone $employees)->count(), (clone $employees)->where('employment_status', 'active')->count(), DB::table('leave_requests')->where('status', 'pending')->count(), DB::table('recruitment_requisitions')->where('status', 'open')->count(), Article::query()->count(), Article::query()->where('status', 'published')->count(), Article::query()->where('status', 'draft')->count(), Article::query()->where('featured', true)->count(), $statuses, Activity::query()->latest()->limit(8)->get()->map(fn (Activity $activity): array => ['description' => $activity->description, 'subject_type' => class_basename((string) $activity->subject_type), 'created_at' => $activity->created_at?->toAtomString()])->all());
    }
}
