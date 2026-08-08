<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;

final class TalentSnapshotCalculator
{
    /** @return array<string,int|float> */
    public function calculate(int $c): array
    {
        $avg = DB::table('performance_reviews')->where('company_id', $c)->whereNotNull('overall_rating')->avg('overall_rating');
        $goals = DB::table('employee_goals')->where('company_id', $c)->count();
        $done = DB::table('employee_goals')->where('company_id', $c)->where('status', 'completed')->count();
        $critical = DB::table('job_positions')->where('company_id', $c)->where('is_critical', true)->count();
        $covered = DB::table('succession_plans')->where('company_id', $c)->whereIn('status', ['approved', 'active'])->distinct()->count('position_id');

        return ['average_rating' => is_numeric($avg) ? round((float) $avg, 2) : 0, 'goal_completion' => $goals > 0 ? round($done / $goals * 100, 2) : 0, 'succession_coverage' => $critical > 0 ? round($covered / $critical * 100, 2) : 0, 'succession_ready_count' => DB::table('succession_candidates as sc')->join('succession_plans as s', 's.id', '=', 'sc.succession_plan_id')->where('s.company_id', $c)->where('sc.readiness_level', 'ready_now')->count()];
    }
}
