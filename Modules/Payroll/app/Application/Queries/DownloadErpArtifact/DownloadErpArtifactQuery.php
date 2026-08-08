<?php

namespace Modules\Payroll\Application\Queries\DownloadErpArtifact;

final readonly class DownloadErpArtifactQuery
{
    public function __construct(public int $companyId, public int $exportId, public int $actorId) {}
}
