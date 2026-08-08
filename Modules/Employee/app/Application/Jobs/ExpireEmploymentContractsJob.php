<?php

namespace Modules\Employee\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Modules\Employee\Domain\Models\EmploymentContract;

final class ExpireEmploymentContractsJob implements ShouldQueue
{
    use FoundationQueueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        EmploymentContract::query()->where('status', 'active')->whereNotNull('ends_at')->whereDate('ends_at', '<', today())->chunkById(200, function ($contracts): void {
            foreach ($contracts as $contract) {
                $contract->update(['status' => 'expired']);
            }
        });
    }
}
