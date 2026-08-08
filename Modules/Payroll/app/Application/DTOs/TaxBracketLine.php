<?php

namespace Modules\Payroll\Application\DTOs;

final readonly class TaxBracketLine
{
    public function __construct(public int $sequence, public int $lowerBoundMinor, public ?int $upperBoundMinor, public int $taxablePortionMinor, public int $rateBasisPoints, public int $amountMinor) {}
}
