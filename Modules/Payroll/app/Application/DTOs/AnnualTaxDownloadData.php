<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class AnnualTaxDownloadData
{
    public function __construct(public string $bytes, public string $fileName, public string $mime, public int $size) {}
}
