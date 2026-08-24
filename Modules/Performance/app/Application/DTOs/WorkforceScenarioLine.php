<?php

namespace Modules\Performance\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class WorkforceScenarioLine
{
    public function __construct(
        public string $scenarioName,
        public string $action,
        public int $positionId,
        public ?int $fromPositionId,
        public int $quantity,
        public CarbonImmutable $effectiveMonth,
    ) {}

    /** @param array{scenario_name?:string,action:string,position_id:int,from_position_id?:int|null,quantity:int,effective_month:string} $line */
    public static function fromArray(array $line): self
    {
        return new self(
            $line['scenario_name'] ?? 'A',
            $line['action'],
            $line['position_id'],
            $line['from_position_id'] ?? null,
            $line['quantity'],
            CarbonImmutable::parse($line['effective_month'])->startOfMonth(),
        );
    }
}
