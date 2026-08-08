<?php

namespace Modules\Notification\Domain\Contracts;

interface NotificationRecipientResolverInterface
{
    /** @return list<int> */
    public function resolve(int $companyId, string $value, string $entityType, int $entityId): array;
}
