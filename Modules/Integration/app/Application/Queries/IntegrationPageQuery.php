<?php

namespace Modules\Integration\Application\Queries;

final readonly class IntegrationPageQuery
{
    public function __construct(public int $companyId, public string $page, public ?int $id = null) {}
}
