<?php

namespace Modules\Payroll\Application\Commands\CreatePayrollAdjustment;

final readonly class CreatePayrollAdjustmentCommand
{
    public function __construct(public int $companyId, public int $sourceItemId, public int $reasonId, public string $type, public string $effectiveDate, public ?int $targetPeriodId, public string $description, public ?int $evidenceDocumentId, public string $idempotencyKey, public int $actorId, public ?int $supersedesId = null) {}
}
