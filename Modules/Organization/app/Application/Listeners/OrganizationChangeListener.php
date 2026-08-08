<?php

namespace Modules\Organization\Application\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Organization\Domain\Events\OrganizationChanged;

final class OrganizationChangeListener
{
    public function handle(OrganizationChanged $event): void
    {
        Cache::increment('schema_admin:organization:'.$event->companyId.':version');
        activity('organization')->causedBy(User::query()->find($event->actorId))->withProperties(['company_id' => $event->companyId, 'before' => $event->subject['before'] ?? null, 'after' => $event->subject['after'] ?? $event->subject])->event($event->broadcastAs())->log($event->broadcastAs());
    }
}
