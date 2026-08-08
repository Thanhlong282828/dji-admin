<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class ErpArtifact
{
    public function __construct(public string $bytes, public string $mime, public string $extension) {}
}
