<?php

namespace Modules\Payroll\Application\Queries\GetPayrollAdjustment;

use Modules\Payroll\Application\DTOs\PayrollAdjustmentViewModel;
use Modules\Payroll\Domain\Repositories\PayrollAdjustmentReadRepositoryInterface;

final readonly class GetPayrollAdjustmentHandler
{
    public function __construct(private PayrollAdjustmentReadRepositoryInterface $repository) {}

    public function handle(GetPayrollAdjustmentQuery $q): PayrollAdjustmentViewModel
    {
        return match ($q->view) {
            'list' => $this->repository->listing($q->companyId, $q->filters), 'create' => $this->repository->createContext($q->companyId), 'detail' => $this->repository->detail($q->companyId, $q->id ?? 0), default => throw new \InvalidArgumentException('Unsupported adjustment view.')
        };
    }
}
