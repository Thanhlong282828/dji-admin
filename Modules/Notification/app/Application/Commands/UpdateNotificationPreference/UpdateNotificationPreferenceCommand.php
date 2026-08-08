<?php

namespace Modules\Notification\Application\Commands\UpdateNotificationPreference;

final readonly class UpdateNotificationPreferenceCommand
{
    public function __construct(public int $companyId, public int $userId, public string $category, public bool $database, public bool $broadcast, public bool $email) {}
}
