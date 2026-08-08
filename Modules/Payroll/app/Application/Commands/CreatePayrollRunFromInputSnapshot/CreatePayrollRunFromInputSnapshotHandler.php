<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollRunFromInputSnapshot;

use Modules\Payroll\Domain\Models\PayrollRun;
use Modules\Payroll\Domain\Services\PayrollInputOperationsService;

final readonly class CreatePayrollRunFromInputSnapshotHandler
{
    public function __construct(private PayrollInputOperationsService $service) {}

    public function handle(CreatePayrollRunFromInputSnapshotCommand $command): PayrollRun
    {
        return $this->service->createRun($command->companyId, $command->snapshotId, $command->actorId, $command->idempotencyKey);
    }
}
