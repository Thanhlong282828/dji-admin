<?php

namespace Modules\Performance\Application\DTOs;

final readonly class WorkforceAnalyticsViewModel
{
    /**
     * @param  array<string, int|float>  $metrics
     * @param  array<string, list<int|float>>  $charts
     * @param  array<string, mixed>  $enterprise
     */
    public function __construct(public array $metrics, public array $charts, public ?string $snapshotDate, public array $enterprise = []) {}
}
