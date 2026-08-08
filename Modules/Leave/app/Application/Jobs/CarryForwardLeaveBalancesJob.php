<?php

namespace Modules\Leave\Application\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Leave\Domain\Services\AutomatedLeaveLedgerService;

final class CarryForwardLeaveBalancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(AutomatedLeaveLedgerService $service): void
    {
        $service->carryForward(CarbonImmutable::today());
    }
}
