<?php

namespace App\Support\Commerce;

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
