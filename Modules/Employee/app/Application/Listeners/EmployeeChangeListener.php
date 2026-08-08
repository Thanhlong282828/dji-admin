<?php

namespace Modules\Employee\Application\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\Employee\Domain\Events\EmployeeChanged;

final class EmployeeChangeListener
{
    public function handle(EmployeeChanged $event): void
    {
        Cache::increment('schema_admin:employee:'.$event->companyId.':version');
        Cache::increment('schema_admin:organization:'.$event->companyId.':version');
        $safe = array_diff_key($event->employee, array_flip(['identity_number', 'identity_number_hash']));
        $eventName = $event->broadcastAs();
        activity('employee')->event($eventName)->withProperties(['company_id' => $event->companyId, 'after' => $safe])->log($eventName);
    }
}
