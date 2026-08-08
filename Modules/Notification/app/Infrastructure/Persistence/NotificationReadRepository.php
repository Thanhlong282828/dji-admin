<?php

namespace Modules\Notification\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Application\DTOs\NotificationCenterViewModel;
use stdClass;

final class NotificationReadRepository
{
    public function inbox(int $companyId, int $userId, ?string $filter, ?string $category, int $page, int $perPage): NotificationCenterViewModel
    {
        $query = DB::table('notification_recipients as r')->join('notification_messages as m', 'm.id', '=', 'r.notification_message_id')->leftJoin('notification_read_states as s', fn (JoinClause $join): JoinClause => $join->on('s.notification_message_id', '=', 'm.id')->on('s.user_id', '=', 'r.user_id'))->where('r.company_id', $companyId)->where('r.user_id', $userId);
        if ($filter === 'unread') {
            $query->whereNull('s.id');
        }
        if ($filter === 'read') {
            $query->whereNotNull('s.id');
        }
        if ($category !== null && $category !== '') {
            $query->where('m.category', $category);
        }
        $total = (clone $query)->count();
        $rows = $query->select('m.id', 'm.uuid', 'm.category', 'm.entity_type', 'm.entity_id', 'm.action', 'm.title', 'm.body', 'm.navigation_reference', 'm.status', 'm.created_at', 's.read_at')->orderByDesc('m.id')->forPage($page, $perPage)->get();
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->row($row);
        }

        return new NotificationCenterViewModel($items, $this->unread($companyId, $userId), $total, $page, $perPage);
    }

    public function detail(int $companyId, int $userId, int $messageId): NotificationCenterViewModel
    {
        $row = DB::table('notification_recipients as r')->join('notification_messages as m', 'm.id', '=', 'r.notification_message_id')->leftJoin('notification_read_states as s', fn (JoinClause $join): JoinClause => $join->on('s.notification_message_id', '=', 'm.id')->on('s.user_id', '=', 'r.user_id'))->where('r.company_id', $companyId)->where('r.user_id', $userId)->where('m.id', $messageId)->select('m.id', 'm.uuid', 'm.category', 'm.entity_type', 'm.entity_id', 'm.action', 'm.title', 'm.body', 'm.navigation_reference', 'm.status', 'm.created_at', 's.read_at')->first();
        abort_if($row === null, 404);

        return new NotificationCenterViewModel([$this->row($row)], $this->unread($companyId, $userId), 1, 1, 1);
    }

    public function unread(int $companyId, int $userId): int
    {
        return DB::table('notification_recipients as r')->leftJoin('notification_read_states as s', fn (JoinClause $join): JoinClause => $join->on('s.notification_message_id', '=', 'r.notification_message_id')->on('s.user_id', '=', 'r.user_id'))->where('r.company_id', $companyId)->where('r.user_id', $userId)->whereNull('s.id')->count();
    }

    public function templates(int $companyId): NotificationCenterViewModel
    {
        $rows = DB::table('notification_templates')->where(fn (Builder $query): Builder => $query->where('company_id', $companyId)->orWhereNull('company_id'))->select('id', 'uuid', 'code', 'name', 'category', 'mandatory', 'status', 'current_version', 'created_at')->orderBy('code')->get();
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->row($row);
        }

        return new NotificationCenterViewModel($items, 0, count($items), 1, 100);
    }

    public function deliveries(int $companyId): NotificationCenterViewModel
    {
        $rows = DB::table('notification_deliveries as d')->join('notification_messages as m', 'm.id', '=', 'd.notification_message_id')->join('users as u', 'u.id', '=', 'd.recipient_id')->where('d.company_id', $companyId)->select('d.id', 'm.title', 'u.name as recipient', 'd.channel', 'd.status', 'd.attempts', 'd.sent_at', 'd.failed_at', 'd.created_at')->orderByDesc('d.id')->limit(200)->get();
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->row($row);
        }

        return new NotificationCenterViewModel($items, 0, count($items), 1, 200);
    }

    /** @return array<string, scalar|null> */
    private function row(stdClass $row): array
    {
        $out = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
