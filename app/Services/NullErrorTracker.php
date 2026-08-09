<?php

namespace App\Services;

use App\Contracts\ErrorTrackerInterface;
use Throwable;

final class NullErrorTracker implements ErrorTrackerInterface
{
    public function capture(Throwable $exception, array $context = []): void
    {
        // Deliberately no-op. An approved provider may replace this binding later.
    }
}
