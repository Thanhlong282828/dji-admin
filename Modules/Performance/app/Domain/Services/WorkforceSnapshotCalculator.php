<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;

final class WorkforceSnapshotCalculator
{
    /** @return array<string,int|float> */
    public function calculate(int $c): array
    {
        $total = DB::table('employees')->where('company_id', $c)->whereNull('deleted_at')->count();
        $inactive = DB::table('employees')->where('company_id', $c)->whereIn('employment_status', ['terminated', 'archived', 'inactive'])->count();
        $active = max(0, $total - $inactive);
        $hires = DB::table('employee_lifecycle_events')->where('company_id', $c)->where('event_type', 'employee.hired')->count();
        $term = DB::table('employee_lifecycle_events')->where('company_id', $c)->where('to_state', 'terminated')->count();
        $prom = DB::table('employee_lifecycle_events')->where('company_id', $c)->where('event_type', 'employee.promoted')->count();
        $transfer = DB::table('employee_lifecycle_events')->where('company_id', $c)->where('event_type', 'employee.transferred')->count();

        return ['headcount' => $total, 'active' => $active, 'inactive' => $inactive, 'new_hires' => $hires, 'terminations' => $term, 'transfers' => $transfer, 'promotions' => $prom, 'attrition_rate' => $total > 0 ? round($term / $total * 100, 2) : 0, 'promotion_rate' => $active > 0 ? round($prom / $active * 100, 2) : 0, 'transfer_rate' => $active > 0 ? round($transfer / $active * 100, 2) : 0];
    }
}
