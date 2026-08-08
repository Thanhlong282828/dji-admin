<?php

namespace Modules\Payroll\Application\Commands\ValidatePayrollInputSnapshot;

use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Services\PayrollInputOperationsService;

final readonly class ValidatePayrollInputSnapshotHandler
{
    public function __construct(private PayrollInputOperationsService $service) {}

    public function handle(ValidatePayrollInputSnapshotCommand $command): PayrollInputSnapshot
    {
        return $this->service->validate($command->companyId, $command->snapshotId, $command->actorId, $command->idempotencyKey);
    }
}
