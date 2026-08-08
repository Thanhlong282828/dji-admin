<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class ErpExportResult
{
    public function __construct(public int $exportId, public string $status, public bool $existing = false) {}
}
