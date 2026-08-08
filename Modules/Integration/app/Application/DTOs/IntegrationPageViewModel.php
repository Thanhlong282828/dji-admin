<?php

namespace Modules\Integration\Application\DTOs;

final readonly class IntegrationPageViewModel
{
    /**
     * @param  list<array<array-key, bool|float|int|string|null>>  $items
     * @param  array<string, int|float>  $metrics
     */
    public function __construct(public string $title, public array $items, public array $metrics = []) {}
}
