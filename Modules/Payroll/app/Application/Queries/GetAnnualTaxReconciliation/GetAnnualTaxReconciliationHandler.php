<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxReconciliation;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class GetAnnualTaxReconciliationHandler
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string, mixed> */
    public function handle(GetAnnualTaxReconciliationQuery $query): array
    {
        if ($query->mode === 'create') {
            return ['employees' => $this->db->table('employees')->where('company_id', $query->companyId)->where('employment_status', 'active')->orderBy('full_name')->get(['id', 'employee_code', 'full_name'])->map(fn (object $row): array => (array) $row)->all()];
        }
        if ($query->mode === 'list') {
            $builder = $this->db->table('payroll_annual_tax_reconciliations as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.company_id', $query->companyId)->select(['a.*', 'e.employee_code', 'e.full_name']);
            foreach (['status', 'result_type', 'tax_year'] as $filter) {
                $value = $query->filters[$filter] ?? '';
                if ($value !== '') {
                    $builder->where('a.'.$filter, $value);
                }
            }
            /** @var LengthAwarePaginator<int, object> $page */
            $page = $builder->orderByDesc('a.updated_at')->paginate(20);

            return ['page' => $page, 'rows' => array_map(fn (object $row): array => (array) $row, $page->items())];
        }
        $row = $this->db->table('payroll_annual_tax_reconciliations as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->where('a.company_id', $query->companyId)->where('a.id', $query->reconciliationId)->select(['a.*', 'e.employee_code', 'e.full_name'])->first();
        if (! is_object($row)) {
            abort(404);
        }

        $sourcesPage = $this->page('payroll_annual_tax_sources', $query, 'source_sort_key', 'sources_page', 25);
        $componentsPage = $this->page('payroll_annual_tax_components', $query, 'sort_order', 'components_page', 50);
        $issuesPage = $this->page('payroll_annual_tax_validation_issues', $query, 'id', 'issues_page', 25, true);
        $historyPage = $this->page('payroll_annual_tax_histories', $query, 'id', 'history_page', 25, true);

        return ['reconciliation' => (array) $row, 'sources' => array_map(fn (object $item): array => (array) $item, $sourcesPage->items()), 'sources_page' => $sourcesPage, 'components' => array_map(fn (object $item): array => (array) $item, $componentsPage->items()), 'components_page' => $componentsPage, 'issues' => array_map(fn (object $item): array => (array) $item, $issuesPage->items()), 'issues_page' => $issuesPage, 'history' => array_map(fn (object $item): array => (array) $item, $historyPage->items()), 'history_page' => $historyPage, 'intents' => $this->db->table('payroll_annual_tax_downstream_intents')->where('company_id', $query->companyId)->where('annual_tax_reconciliation_id', $query->reconciliationId)->orderBy('intent_type')->limit(10)->get()->map(fn (object $item): array => (array) $item)->all()];
    }

    /** @return LengthAwarePaginator<int, object> */
    private function page(string $table, GetAnnualTaxReconciliationQuery $query, string $order, string $pageName, int $perPage, bool $descending = false): LengthAwarePaginator
    {
        $builder = $this->db->table($table)->where('company_id', $query->companyId)->where('annual_tax_reconciliation_id', $query->reconciliationId);
        $builder = $descending ? $builder->orderByDesc($order) : $builder->orderBy($order);
        $requested = $query->filters[$pageName] ?? 1;
        $page = is_int($requested) ? max(1, $requested) : 1;
        /** @var LengthAwarePaginator<int, object> $result */
        $result = $builder->paginate(min(100, $perPage), ['*'], $pageName, $page);

        return $result;
    }
}
