<?php

namespace Modules\Payroll\Application\Commands\CapturePayrollInputSnapshot;

use Modules\Payroll\Domain\Models\PayrollInputSnapshot;
use Modules\Payroll\Domain\Services\PayrollInputOperationsService;

final readonly class CapturePayrollInputSnapshotHandler
{
    public function __construct(private PayrollInputOperationsService $service) {}

    public function handle(CapturePayrollInputSnapshotCommand $command): PayrollInputSnapshot
    {
        return $this->service->capture($command->companyId, $command->periodId, $command->actorId, $command->idempotencyKey, $command->note);
    }
}
