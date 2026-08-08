<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class PayslipSnapshotData
{
    /** @param array<string,mixed> $payload */
    public function __construct(public array $payload) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
