<?php

namespace Modules\Employee\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Employee\Application\DTOs\HRGovernanceDashboardViewModel;
use Modules\Employee\Domain\Repositories\HRGovernanceReadRepositoryInterface;
use stdClass;

final class EloquentHRGovernanceReadRepository implements HRGovernanceReadRepositoryInterface
{
    public function dashboard(int $companyId): HRGovernanceDashboardViewModel
    {
        $issues = $this->rows(DB::table('hr_data_quality_issues as i')->join('hr_data_quality_runs as r', 'r.id', '=', 'i.run_id')->where('r.company_id', $companyId)->select('i.id', 'i.rule_code', 'i.entity_type', 'i.entity_id', 'i.severity', 'i.status', 'i.message', 'i.created_at')->latest('i.id')->limit(100)->get());
        $open = 0;
        $blocking = 0;
        foreach ($issues as $issue) {
            if (($issue['status'] ?? '') !== 'resolved') {
                $open++;
            }
            if (($issue['severity'] ?? '') === 'blocking' && ($issue['status'] ?? '') !== 'resolved') {
                $blocking++;
            }
        }
        $employees = DB::table('employees')->where('company_id', $companyId)->whereNull('deleted_at')->count();
        $consents = $this->rows(DB::table('data_consents as d')->join('employees as e', 'e.id', '=', 'd.employee_id')->where('e.company_id', $companyId)->select('d.id', 'd.employee_id', 'd.purpose', 'd.policy_version', 'd.granted', 'd.decided_at', 'd.expires_at')->latest('d.id')->limit(100)->get());
        $documents = $this->rows(DB::table('employee_documents as d')->join('employees as e', 'e.id', '=', 'd.employee_id')->where('e.company_id', $companyId)->select('d.id', 'd.employee_id', 'd.type', 'd.name', 'd.version', 'd.expires_at', 'd.archived_at', 'd.scan_status')->latest('d.id')->limit(100)->get());
        $audits = $this->rows(DB::table('hr_governance_audit_events')->where('company_id', $companyId)->select('id', 'actor_id', 'entity_type', 'entity_id', 'action', 'checksum', 'created_at')->latest('id')->limit(100)->get());

        return new HRGovernanceDashboardViewModel(
            max(0, 100 - $open * 5), $open, $blocking,
            $employees > 0 ? (int) round(count($consents) / $employees * 100) : 100,
            $employees > 0 ? (int) round(count($documents) / $employees * 100) : 100,
            $issues, $audits, $consents, $documents,
        );
    }

    /**
     * @param  iterable<int, stdClass>  $source
     * @return list<array<string, scalar|null>>
     */
    private function rows(iterable $source): array
    {
        $rows = [];
        foreach ($source as $sourceRow) {
            $row = [];
            foreach (get_object_vars($sourceRow) as $key => $value) {
                if (is_string($key) && (is_scalar($value) || $value === null)) {
                    $row[$key] = $value;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
