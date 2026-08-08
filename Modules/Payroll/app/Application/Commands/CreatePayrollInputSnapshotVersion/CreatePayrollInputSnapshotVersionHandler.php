<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollInputSnapshotVersion;

use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Services\PayrollInputOperationsService;

final readonly class CreatePayrollInputSnapshotVersionHandler
{
    public function __construct(private PayrollInputOperationsService $service) {}

    public function handle(CreatePayrollInputSnapshotVersionCommand $command): PayrollInputSnapshot
    {
        return $this->service->capture($command->companyId, $command->periodId, $command->actorId, $command->idempotencyKey, $command->note);
    }
}
