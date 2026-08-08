<?php

namespace Modules\Onboarding\Infrastructure\Persistence\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Onboarding\Domain\Models\OnboardingCase;
use Modules\Onboarding\Domain\Repositories\OnboardingReadRepositoryInterface;

final class EloquentOnboardingReadRepository implements OnboardingReadRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, OnboardingCase>
     */
    public function cases(int $companyId, array $filters): LengthAwarePaginator
    {
        $query = OnboardingCase::query()
            ->with(['employee:id,company_id,employee_code,full_name,employment_status', 'buddy:id,full_name'])
            ->where('company_id', $companyId);
        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return $query->orderBy('start_date')->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   preboarding: int, in_progress: int, probation_due: int,
     *   completed_month: int, overdue_items: int, pending_provisioning: int,
     *   status_counts: array<string, int>
     * }
     */
    public function dashboard(int $companyId, array $filters): array
    {
        $cases = DB::table('onboarding_cases')->where('company_id', $companyId);
        /** @var array<string, int> $statusCounts */
        $statusCounts = (clone $cases)
            ->select('status', DB::raw('COUNT(*) total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0)
            ->all();
        $caseIds = (clone $cases)->select('id');
        $overdue = DB::table('onboarding_checklist_items')
            ->whereIn('onboarding_case_id', $caseIds)
            ->where('required', true)
            ->where('status', '<>', 'completed')
            ->whereDate('due_at', '<', today())
            ->count();
        $pending = DB::table('onboarding_provisioning_items')
            ->whereIn('onboarding_case_id', $caseIds)
            ->where('status', '<>', 'completed')
            ->count();

        return [
            'preboarding' => $statusCounts['preboarding'] ?? 0,
            'in_progress' => $statusCounts['in_progress'] ?? 0,
            'probation_due' => $statusCounts['probation_review'] ?? 0,
            'completed_month' => (clone $cases)->where('status', 'completed')->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'overdue_items' => $overdue,
            'pending_provisioning' => $pending,
            'status_counts' => $statusCounts,
        ];
    }
}
