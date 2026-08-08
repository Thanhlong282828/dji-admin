<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Modules\Payroll\Application\DTOs\InsuranceAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\InsuranceAdministrationReadRepositoryInterface;

final readonly class EloquentInsuranceAdministrationReadRepository implements InsuranceAdministrationReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function policies(int $companyId, array $filters): InsuranceAdministrationViewModel
    {
        $page = max(1, $filters['page']);
        $q = $this->db->table('insurance_policies as p')->where('p.company_id', $companyId)->leftJoin('insurance_policy_rates as r', 'r.insurance_policy_id', '=', 'p.id')->leftJoin('employee_insurance_profiles as e', 'e.insurance_policy_id', '=', 'p.id')->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $x): Builder => $x->where('p.code', 'like', '%'.$filters['search'].'%')->orWhere('p.name', 'like', '%'.$filters['search'].'%')))->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('p.status', $filters['status']))->when($filters['year'] !== '', fn (Builder $q): Builder => $q->whereYear('p.effective_from', '<=', $filters['year'])->where(fn (Builder $x): Builder => $x->whereNull('p.effective_to')->orWhereYear('p.effective_to', '>=', $filters['year'])))->groupBy(['p.id', 'p.code', 'p.name', 'p.version', 'p.effective_from', 'p.effective_to', 'p.status', 'p.currency_code', 'p.rounding_rule'])->orderByDesc('p.effective_from')->orderByDesc('p.id')->selectRaw('p.id,p.code,p.name,p.version,p.effective_from,p.effective_to,p.status,p.currency_code,p.rounding_rule,COUNT(DISTINCT r.id) rate_count,COUNT(DISTINCT e.id) profile_count')->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($q->take(25) as $r) {
            $items[] = ['id' => $this->i($r, 'id'), 'code' => $this->s($r, 'code'), 'name' => $this->s($r, 'name'), 'version' => $this->i($r, 'version'), 'effective_from' => $this->s($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to'), 'status' => $this->s($r, 'status'), 'currency' => $this->s($r, 'currency_code'), 'rounding' => $this->s($r, 'rounding_rule'), 'rate_count' => $this->i($r, 'rate_count'), 'profile_count' => $this->i($r, 'profile_count')];
        }

        return new InsuranceAdministrationViewModel($items, page: $page, hasMore: $q->count() > 25);
    }

    public function policy(int $companyId, int $policyId): InsuranceAdministrationViewModel
    {
        $p = $this->db->table('insurance_policies')->where('company_id', $companyId)->find($policyId);
        if (! is_object($p)) {
            abort(404);
        }
        $rates = [];
        foreach ($this->db->table('insurance_policy_rates')->where('insurance_policy_id', $policyId)->orderBy('calculation_order')->get() as $r) {
            $rates[] = ['insurance_type' => $this->s($r, 'insurance_type'), 'employee_rate_basis_points' => $this->i($r, 'employee_rate_basis_points'), 'employer_rate_basis_points' => $this->i($r, 'employer_rate_basis_points'), 'minimum_base_minor' => $this->ni($r, 'minimum_base_minor'), 'maximum_base_minor' => $this->ni($r, 'maximum_base_minor')];
        }

        return new InsuranceAdministrationViewModel(detail: ['id' => $this->i($p, 'id'), 'code' => $this->s($p, 'code'), 'name' => $this->s($p, 'name'), 'version' => $this->i($p, 'version'), 'effective_from' => $this->s($p, 'effective_from'), 'effective_to' => $this->n($p, 'effective_to'), 'status' => $this->s($p, 'status'), 'currency_code' => $this->s($p, 'currency_code'), 'rounding_rule' => $this->s($p, 'rounding_rule'), 'note' => $this->n($p, 'note'), 'rates' => $rates]);
    }

    public function employees(int $companyId, array $filters): InsuranceAdministrationViewModel
    {
        $page = max(1, $filters['page']);
        $q = $this->db->table('employees as e')->where('e.company_id', $companyId)->leftJoin('employee_insurance_profiles as p', fn (JoinClause $join): JoinClause => $join->on('p.employee_id', '=', 'e.id')->whereNull('p.effective_to'))->leftJoin('insurance_policies as i', 'i.id', '=', 'p.insurance_policy_id')->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $x): Builder => $x->where('e.employee_code', 'like', '%'.$filters['search'].'%')->orWhere('e.full_name', 'like', '%'.$filters['search'].'%')))->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('p.participation_status', $filters['status']))->orderBy('e.employee_code')->orderBy('e.id')->select(['e.id', 'e.employee_code', 'e.full_name', 'p.id as profile_id', 'p.participation_status', 'p.insurance_number', 'p.effective_from', 'p.effective_to', 'p.contribution_base_override_minor', 'i.name as policy_name'])->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($q->take(25) as $r) {
            $number = $this->n($r, 'insurance_number');
            $items[] = ['id' => $this->i($r, 'id'), 'profile_id' => $this->ni($r, 'profile_id'), 'number' => $this->s($r, 'employee_code'), 'name' => $this->s($r, 'full_name'), 'status' => $this->n($r, 'participation_status'), 'insurance_number' => $number === null ? null : '***'.substr($number, -4), 'policy' => $this->n($r, 'policy_name'), 'effective_from' => $this->n($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to'), 'override' => $this->ni($r, 'contribution_base_override_minor')];
        }

        return new InsuranceAdministrationViewModel(items: $items, policies: $this->policyOptions($companyId), page: $page, hasMore: $q->count() > 25);
    }

    public function employee(int $companyId, int $employeeId): InsuranceAdministrationViewModel
    {
        $e = $this->db->table('employees')->where('company_id', $companyId)->find($employeeId);
        if (! is_object($e)) {
            abort(404);
        }$profiles = [];
        foreach ($this->db->table('employee_insurance_profiles as p')->leftJoin('insurance_policies as i', 'i.id', '=', 'p.insurance_policy_id')->where('p.company_id', $companyId)->where('p.employee_id', $employeeId)->orderByDesc('p.effective_from')->select(['p.*', 'i.name as policy_name'])->get() as $p) {
            $profiles[] = ['id' => $this->i($p, 'id'), 'status' => $this->s($p, 'participation_status'), 'policy_id' => $this->ni($p, 'insurance_policy_id'), 'policy' => $this->n($p, 'policy_name'), 'effective_from' => $this->s($p, 'effective_from'), 'effective_to' => $this->n($p, 'effective_to'), 'insurance_number' => $this->n($p, 'insurance_number'), 'override' => $this->ni($p, 'contribution_base_override_minor'), 'currency' => $this->n($p, 'currency_code'), 'reason' => $this->n($p, 'exemption_reason')];
        }

        return new InsuranceAdministrationViewModel(detail: ['id' => $this->i($e, 'id'), 'number' => $this->s($e, 'employee_code'), 'name' => $this->s($e, 'full_name'), 'profiles' => $profiles], policies: $this->policyOptions($companyId));
    }

    /** @return list<array{id:int,code:string,name:string}> */
    private function policyOptions(int $companyId): array
    {
        $a = [];
        foreach ($this->db->table('insurance_policies')->where('company_id', $companyId)->orderByDesc('effective_from')->get() as $p) {
            $a[] = ['id' => $this->i($p, 'id'), 'code' => $this->s($p, 'code'), 'name' => $this->s($p, 'name')];
        }

        return $a;
    }

    private function i(object $r, string $k): int
    {
        return $this->ni($r, $k) ?? 0;
    }

    private function ni(object $r, string $k): ?int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    private function s(object $r, string $k): string
    {
        return $this->n($r, $k) ?? '';
    }

    private function n(object $r, string $k): ?string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : null;
    }
}
