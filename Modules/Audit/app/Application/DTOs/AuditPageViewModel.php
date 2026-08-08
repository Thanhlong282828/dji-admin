<?php

namespace Modules\Audit\Application\DTOs;

final readonly class AuditPageViewModel
{
    /**
     * @param  list<array<string, bool|float|int|string|null>>  $items
     * @param  array<string, int|float>  $metrics
     */
    public function __construct(public string $title, public array $items, public array $metrics = []) {}
}
