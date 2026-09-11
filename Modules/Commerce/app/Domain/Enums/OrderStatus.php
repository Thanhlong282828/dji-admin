<?php

namespace Modules\Commerce\Domain\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function buyerCanCancel(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed], true);
    }
}
