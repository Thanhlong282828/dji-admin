<?php

namespace Modules\Notification\Application\DTOs;

final readonly class NotificationCenterViewModel
{
    /** @param list<array<string, scalar|null>> $items */
    public function __construct(public array $items, public int $unreadCount, public int $total, public int $page, public int $perPage) {}
}
