<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;

final class OrganizationSnapshotCalculator
{
    /** @return array<string,int|float> */
    public function calculate(int $c, int $active): array
    {
        $positions = DB::table('job_positions')->where('company_id', $c)->count();
        $approved = (int) DB::table('job_positions')->where('company_id', $c)->sum('approved_headcount');
        $managers = DB::table('employee_manager_history')->where('company_id', $c)->whereNull('effective_to')->distinct()->count('manager_id');

        return ['position_count' => $positions, 'filled_position' => min($active, $positions), 'vacancy' => max(0, $approved - $active), 'span_of_control' => $managers > 0 ? round($active / $managers, 2) : 0];
    }
}
