<?php

namespace Modules\Payroll\Application\Commands\CreateErpExport;

final readonly class CreateErpExportCommand
{
    public function __construct(public int $companyId, public int $connectionId, public int $journalId, public int $actorId, public string $idempotencyKey) {}
}
