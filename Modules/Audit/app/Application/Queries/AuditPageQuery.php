<?php

namespace Modules\Audit\Application\Queries;

final readonly class AuditPageQuery
{
    public function __construct(public int $companyId, public string $page, public ?int $id = null, public ?string $search = null) {}
}
