<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxDocumentData
{
    public function __construct(public int $id, public int $reconciliationId, public int $version, public string $fileName, public string $mime, public int $size, public string $checksum, public bool $existing) {}
}
