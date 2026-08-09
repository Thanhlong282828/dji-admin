<?php

namespace App\Contracts;

use Throwable;

interface ErrorTrackerInterface
{
    /** @param array<string, scalar|null> $context */
    public function capture(Throwable $exception, array $context = []): void;
}
