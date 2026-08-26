<?php

namespace App\Support\Commerce;

final readonly class CartViewModel
{
    /** @param array<int,array{name:string,slug:string,items:list<array<string,mixed>>,subtotal_minor:int}> $stores */
    public function __construct(
        public int $cartId,
        public array $stores,
        public CartPricingSummary $summary,
        public int $itemCount,
        public int $totalQuantity,
    ) {}
}
