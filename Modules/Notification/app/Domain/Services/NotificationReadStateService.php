<?php

namespace Modules\Notification\Domain\Services;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class NotificationReadStateService
{
    public function mark(int $companyId, int $userId, int $messageId): void
    {
        DB::transaction(function () use ($companyId, $userId, $messageId): void {
            abort_unless(DB::table('notification_recipients')->where('company_id', $companyId)->where('notification_message_id', $messageId)->where('user_id', $userId)->exists(), 404);
            $payload = compact('companyId', 'userId', 'messageId');
            DB::table('notification_read_states')->insertOrIgnore(['company_id' => $companyId, 'notification_message_id' => $messageId, 'user_id' => $userId, 'read_at' => now(), 'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->where('notifiable_id', $userId)->where('data->uuid', DB::table('notification_messages')->where('id', $messageId)->value('uuid'))->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function markAll(int $companyId, int $userId): int
    {
        return DB::transaction(function () use ($companyId, $userId): int {
            $ids = DB::table('notification_recipients as r')->leftJoin('notification_read_states as s', fn (JoinClause $join): JoinClause => $join->on('s.notification_message_id', '=', 'r.notification_message_id')->on('s.user_id', '=', 'r.user_id'))->where('r.company_id', $companyId)->where('r.user_id', $userId)->whereNull('s.id')->lockForUpdate()->pluck('r.notification_message_id');
            foreach ($ids as $id) {
                if (is_numeric($id)) {
                    $this->mark($companyId, $userId, (int) $id);
                }
            }

            return count($ids);
        }, 3);
    }
}
