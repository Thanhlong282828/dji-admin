<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class OperationsProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $probeId) {}

    public function handle(): void
    {
        // Intentionally side-effect free; used only by guarded readiness benchmarks.
    }
}
