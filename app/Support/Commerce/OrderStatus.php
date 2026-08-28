<?php

namespace App\Support\Commerce;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Cancelled = 'cancelled';

    public function buyerCanCancel(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed], true);
    }
}
