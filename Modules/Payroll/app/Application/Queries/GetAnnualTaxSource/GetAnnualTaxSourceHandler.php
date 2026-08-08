<?php

namespace Modules\Payroll\Application\Queries\GetAnnualTaxSource;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Payroll\Application\DTOs\AnnualTaxSourceViewModel;

final readonly class GetAnnualTaxSourceHandler
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(GetAnnualTaxSourceQuery $query): AnnualTaxSourceViewModel
    {
        $sources = $this->page('payroll_annual_tax_sources', $query, 'source_sort_key', 'sources_page', 25, $query->sourcesPage);
        $components = $this->page('payroll_annual_tax_components', $query, 'sort_order', 'components_page', 50, $query->componentsPage);
        $issues = $this->page('payroll_annual_tax_validation_issues', $query, 'id', 'issues_page', 25, $query->issuesPage, true);

        return new AnnualTaxSourceViewModel($this->rows($sources), $sources, $this->rows($components), $components, $this->rows($issues), $issues);
    }

    /** @return LengthAwarePaginator<int,object> */
    private function page(string $table, GetAnnualTaxSourceQuery $query, string $order, string $pageName, int $perPage, int $page, bool $desc = false): LengthAwarePaginator
    {
        $builder = $this->db->table($table)->where('company_id', $query->companyId)->where('annual_tax_reconciliation_id', $query->reconciliationId);
        $builder = $desc ? $builder->orderByDesc($order) : $builder->orderBy($order);
        /** @var LengthAwarePaginator<int,object> $result */ $result = $builder->paginate(min(100, $perPage), ['*'], $pageName, max(1, $page));

        return $result;
    }

    /**
     * @param  LengthAwarePaginator<int,object>  $page
     * @return list<array<string,mixed>>
     */
    private function rows(LengthAwarePaginator $page): array
    {
        $result = [];
        foreach ($page->items() as $row) {
            $result[] = $this->row($row);
        }

        return $result;
    }

    /** @return array<string,mixed> */
    private function row(object $row): array
    {
        $result = [];
        foreach ((array) $row as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
