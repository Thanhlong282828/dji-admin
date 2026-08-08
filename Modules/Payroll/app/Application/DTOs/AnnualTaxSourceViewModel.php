<?php

namespace Modules\Payroll\Application\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class AnnualTaxSourceViewModel
{
    /**
     * @param  list<array<string,mixed>>  $sources
     * @param  LengthAwarePaginator<int,object>  $sourcesPage
     * @param  list<array<string,mixed>>  $components
     * @param  LengthAwarePaginator<int,object>  $componentsPage
     * @param  list<array<string,mixed>>  $issues
     * @param  LengthAwarePaginator<int,object>  $issuesPage
     */
    public function __construct(public array $sources, public LengthAwarePaginator $sourcesPage, public array $components, public LengthAwarePaginator $componentsPage, public array $issues, public LengthAwarePaginator $issuesPage) {}

    /** @return array<string,mixed> */
    public function toViewData(): array
    {
        return ['sources' => $this->sources, 'sources_page' => $this->sourcesPage, 'components' => $this->components, 'components_page' => $this->componentsPage, 'issues' => $this->issues, 'issues_page' => $this->issuesPage];
    }
}
