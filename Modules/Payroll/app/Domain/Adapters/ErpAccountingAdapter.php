<?php

namespace Modules\Payroll\Domain\Adapters;

use Modules\Payroll\Application\DTOs\ErpAdapterPayload;
use Modules\Payroll\Application\DTOs\ErpArtifact;

interface ErpAccountingAdapter
{
    public function provider(): string;

    public function export(ErpAdapterPayload $payload): ErpArtifact;
}
