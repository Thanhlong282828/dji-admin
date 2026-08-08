<?php

namespace Modules\Company\Application\Commands\UpdateCompany;

final readonly class UpdateCompanyCommand
{
    /** @param array<string,mixed> $attributes */
    public function __construct(public int $companyId, public array $attributes, public int $actorId) {}
}
