<?php

namespace Modules\Commerce\Application\DTOs;

final readonly class CartPricingSummary
{
    public function __construct(
        public int $selectedQuantity,
        public int $subtotalMinor,
        public int $discountMinor = 0,
    ) {}

    public function totalMinor(): int
    {
        return $this->subtotalMinor - $this->discountMinor;
    }
}
