<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class GovernmentFilingDownloadData
{
    public function __construct(public string $bytes, public string $fileName, public string $mimeType) {}
}
