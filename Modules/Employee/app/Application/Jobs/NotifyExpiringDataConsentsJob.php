<?php

namespace Modules\Employee\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Modules\Employee\Domain\Models\DataConsent;

final class NotifyExpiringDataConsentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(): void
    {
        $key = 'schema_admin:employee:expiry:consents:'.today()->toDateString();
        Cache::lock($key, 86400)->get(function (): void {
            $count = DataConsent::query()->where('granted', true)->whereBetween('expires_at', [today(), today()->addDays(30)])->count();
            activity('employee-expiry')->event('consents.detected')->withProperties(['count' => $count, 'window_days' => 30])->log('Expiring data consents detected');
        });
    }
}
