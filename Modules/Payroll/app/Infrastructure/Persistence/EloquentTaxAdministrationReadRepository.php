<?php

namespace Modules\Payroll\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Modules\Payroll\Application\DTOs\TaxAdministrationViewModel;
use Modules\Payroll\Domain\Repositories\TaxAdministrationReadRepositoryInterface;

final readonly class EloquentTaxAdministrationReadRepository implements TaxAdministrationReadRepositoryInterface
{
    public function __construct(private ConnectionInterface $db) {}

    public function policies(int $companyId, array $filters): TaxAdministrationViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('tax_policies as p')->where('p.company_id', $companyId)->leftJoin('tax_policy_brackets as b', 'b.tax_policy_id', '=', 'p.id')->leftJoin('payroll_items as i', 'i.tax_policy_id', '=', 'p.id')->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $x): Builder => $x->where('p.code', 'like', '%'.$filters['search'].'%')->orWhere('p.name', 'like', '%'.$filters['search'].'%')))->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('p.status', $filters['status']))->when($filters['method'] !== '', fn (Builder $q): Builder => $q->where('p.tax_method', $filters['method']))->when($filters['year'] !== '', fn (Builder $q): Builder => $q->whereYear('p.effective_from', '<=', $filters['year']))->groupBy(['p.id', 'p.code', 'p.name', 'p.version', 'p.effective_from', 'p.effective_to', 'p.currency_code', 'p.tax_method', 'p.residency_group', 'p.rounding_rule', 'p.status'])->orderByDesc('p.effective_from')->orderByDesc('p.id')->selectRaw('p.id,p.code,p.name,p.version,p.effective_from,p.effective_to,p.currency_code,p.tax_method,p.residency_group,p.rounding_rule,p.status,COUNT(DISTINCT b.id) bracket_count,COUNT(DISTINCT i.id) usage_count')->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($rows->take(25) as $r) {
            $items[] = ['id' => $this->i($r, 'id'), 'code' => $this->s($r, 'code'), 'name' => $this->s($r, 'name'), 'version' => $this->i($r, 'version'), 'effective_from' => $this->s($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to'), 'currency' => $this->s($r, 'currency_code'), 'method' => $this->s($r, 'tax_method'), 'residency' => $this->s($r, 'residency_group'), 'rounding' => $this->s($r, 'rounding_rule'), 'status' => $this->s($r, 'status'), 'bracket_count' => $this->i($r, 'bracket_count'), 'usage_count' => $this->i($r, 'usage_count')];
        }

        return new TaxAdministrationViewModel($items, page: $page, hasMore: $rows->count() > 25);
    }

    public function policy(int $companyId, int $policyId): TaxAdministrationViewModel
    {
        $p = $this->db->table('tax_policies')->where('company_id', $companyId)->find($policyId);
        if (! is_object($p)) {
            abort(404);
        }$brackets = [];
        foreach ($this->db->table('tax_policy_brackets')->where('tax_policy_id', $policyId)->orderBy('sequence')->get() as $b) {
            $brackets[] = ['sequence' => $this->i($b, 'sequence'), 'lower_bound_minor' => $this->i($b, 'lower_bound_minor'), 'upper_bound_minor' => $this->ni($b, 'upper_bound_minor'), 'rate_basis_points' => $this->i($b, 'rate_basis_points')];
        }$deductions = [];
        foreach ($this->db->table('tax_policy_deductions')->where('tax_policy_id', $policyId)->orderBy('priority')->get() as $d) {
            $deductions[] = ['code' => $this->s($d, 'code'), 'name' => $this->s($d, 'name'), 'deduction_type' => $this->s($d, 'deduction_type'), 'amount_minor' => $this->ni($d, 'amount_minor'), 'rate_basis_points' => $this->ni($d, 'rate_basis_points'), 'maximum_amount_minor' => $this->ni($d, 'maximum_amount_minor')];
        }

        return new TaxAdministrationViewModel(detail: ['id' => $this->i($p, 'id'), 'code' => $this->s($p, 'code'), 'name' => $this->s($p, 'name'), 'version' => $this->i($p, 'version'), 'effective_from' => $this->s($p, 'effective_from'), 'effective_to' => $this->n($p, 'effective_to'), 'currency_code' => $this->s($p, 'currency_code'), 'tax_method' => $this->s($p, 'tax_method'), 'residency_group' => $this->s($p, 'residency_group'), 'rounding_rule' => $this->s($p, 'rounding_rule'), 'floor_taxable_income_at_zero' => (bool) ($p->floor_taxable_income_at_zero ?? false), 'flat_rate_basis_points' => $this->ni($p, 'flat_rate_basis_points'), 'status' => $this->s($p, 'status'), 'note' => $this->n($p, 'note'), 'brackets' => $brackets, 'deductions' => $deductions]);
    }

    public function employees(int $companyId, array $filters): TaxAdministrationViewModel
    {
        $page = max(1, $filters['page']);
        $rows = $this->db->table('employees as e')->where('e.company_id', $companyId)->leftJoin('employee_tax_profiles as p', fn (JoinClause $j): JoinClause => $j->on('p.employee_id', '=', 'e.id')->whereNull('p.effective_to'))->leftJoin('tax_policies as t', 't.id', '=', 'p.tax_policy_id')->when($filters['search'] !== '', fn (Builder $q): Builder => $q->where(fn (Builder $x): Builder => $x->where('e.employee_code', 'like', '%'.$filters['search'].'%')->orWhere('e.full_name', 'like', '%'.$filters['search'].'%')))->when($filters['status'] !== '', fn (Builder $q): Builder => $q->where('p.tax_status', $filters['status']))->when($filters['residency'] !== '', fn (Builder $q): Builder => $q->where('p.residency_status', $filters['residency']))->orderBy('e.employee_code')->select(['e.id', 'e.employee_code', 'e.full_name', 'p.id as profile_id', 'p.tax_status', 'p.residency_status', 'p.tax_identification_number', 'p.effective_from', 'p.effective_to', 't.name as policy_name'])->offset(($page - 1) * 25)->limit(26)->get();
        $items = [];
        foreach ($rows->take(25) as $r) {
            $tax = $this->n($r, 'tax_identification_number');
            $items[] = ['id' => $this->i($r, 'id'), 'profile_id' => $this->ni($r, 'profile_id'), 'number' => $this->s($r, 'employee_code'), 'name' => $this->s($r, 'full_name'), 'status' => $this->n($r, 'tax_status'), 'residency' => $this->n($r, 'residency_status'), 'tax_number' => $tax === null ? null : '***'.substr($tax, -4), 'policy' => $this->n($r, 'policy_name'), 'effective_from' => $this->n($r, 'effective_from'), 'effective_to' => $this->n($r, 'effective_to')];
        }

        return new TaxAdministrationViewModel($items, policies: $this->options($companyId), page: $page, hasMore: $rows->count() > 25);
    }

    public function employee(int $companyId, int $employeeId): TaxAdministrationViewModel
    {
        $e = $this->db->table('employees')->where('company_id', $companyId)->find($employeeId);
        if (! is_object($e)) {
            abort(404);
        }$profiles = [];
        foreach ($this->db->table('employee_tax_profiles as p')->leftJoin('tax_policies as t', 't.id', '=', 'p.tax_policy_id')->where('p.company_id', $companyId)->where('p.employee_id', $employeeId)->orderByDesc('p.effective_from')->select(['p.*', 't.name as policy_name'])->get() as $p) {
            $tax = $this->n($p, 'tax_identification_number');
            $profiles[] = ['id' => $this->i($p, 'id'), 'status' => $this->s($p, 'tax_status'), 'residency' => $this->s($p, 'residency_status'), 'policy_id' => $this->ni($p, 'tax_policy_id'), 'policy' => $this->n($p, 'policy_name'), 'effective_from' => $this->s($p, 'effective_from'), 'effective_to' => $this->n($p, 'effective_to'), 'tax_number' => $tax === null ? null : '***'.substr($tax, -4), 'personal_deduction' => (bool) ($p->personal_deduction_eligible ?? false), 'withholding_method' => $this->s($p, 'withholding_method'), 'reason' => $this->n($p, 'exemption_reason')];
        }$dependents = $this->db->table('employee_tax_dependents')->where('company_id', $companyId)->where('employee_id', $employeeId)->count();

        return new TaxAdministrationViewModel(detail: ['id' => $this->i($e, 'id'), 'number' => $this->s($e, 'employee_code'), 'name' => $this->s($e, 'full_name'), 'profiles' => $profiles, 'dependent_count' => $dependents], policies: $this->options($companyId));
    }

    /** @return list<array{id:int,code:string,name:string}> */
    private function options(int $company): array
    {
        $a = [];
        foreach ($this->db->table('tax_policies')->where('company_id', $company)->orderByDesc('effective_from')->get() as $p) {
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
