<?php

namespace Modules\Company\Application\Commands\CreateCompany;

final readonly class CreateCompanyCommand
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes, public int $actorId) {}
}
