<?php

namespace Modules\Payroll\Application\Queries\DownloadAnnualTaxStatement;

final readonly class DownloadAnnualTaxStatementQuery
{
    public function __construct(public int $companyId, public int $reconciliationId, public int $documentId, public int $actorId, public ?string $ip, public ?string $userAgent) {}
}
