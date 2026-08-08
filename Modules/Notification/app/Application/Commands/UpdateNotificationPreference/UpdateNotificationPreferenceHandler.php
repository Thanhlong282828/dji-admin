<?php

namespace Modules\Notification\Application\Commands\UpdateNotificationPreference;

use Illuminate\Support\Facades\DB;

final class UpdateNotificationPreferenceHandler
{
    public function handle(UpdateNotificationPreferenceCommand $c): void
    {
        abort_if($c->category === 'security' && (! $c->database || ! $c->broadcast), 422, 'Mandatory security notifications cannot be disabled.');
        DB::transaction(function () use ($c): void {
            $existing = DB::table('notification_preferences')->where('company_id', $c->companyId)->where('user_id', $c->userId)->where('category', $c->category)->lockForUpdate()->first();
            if ($existing === null) {
                DB::table('notification_preferences')->insert(['company_id' => $c->companyId, 'user_id' => $c->userId, 'category' => $c->category, 'database_enabled' => $c->database, 'broadcast_enabled' => $c->broadcast, 'email_enabled' => $c->email, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

                return;
            }
            DB::table('notification_preferences')->where('company_id', $c->companyId)->where('user_id', $c->userId)->where('category', $c->category)->update(['database_enabled' => $c->database, 'broadcast_enabled' => $c->broadcast, 'email_enabled' => $c->email, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        }, 3);
    }
}
