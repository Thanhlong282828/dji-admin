<?php

namespace Modules\Payroll\Application\Queries\GetAccountingView;

use Modules\Payroll\Application\DTOs\AccountingViewModel;
use Modules\Payroll\Domain\Repositories\AccountingReadRepositoryInterface;

final readonly class GetAccountingViewHandler
{
    public function __construct(private AccountingReadRepositoryInterface $repository) {}

    public function handle(GetAccountingViewQuery $q): AccountingViewModel
    {
        return match ($q->view) {
            'accounts' => $this->repository->accounts($q->companyId, $q->filters),
            'account' => $this->repository->account($q->companyId, $q->id),
            'policies' => $this->repository->policies($q->companyId, $q->filters),
            'policy' => $this->repository->policy($q->companyId, $q->id),
            'mappings' => $this->repository->mappings($q->companyId, $q->filters),
            'mapping' => $this->repository->mapping($q->companyId, $q->id),
            'journals' => $this->repository->journals($q->companyId, $q->filters),
            'journal' => $this->repository->journal($q->companyId, $q->id ?? 0),
            default => throw new \InvalidArgumentException('Unsupported Accounting view.'),
        };
    }
}
