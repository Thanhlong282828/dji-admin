<?php

namespace Modules\Notification\Infrastructure\Resolvers;

use Illuminate\Support\Facades\DB;
use Modules\Notification\Domain\Contracts\NotificationRecipientResolverInterface;

final class SpecificUserRecipientResolver implements NotificationRecipientResolverInterface
{
    public function resolve(int $companyId, string $value, string $entityType, int $entityId): array
    {
        if (! ctype_digit($value)) {
            return [];
        } $id = (int) $value;

        return DB::table('company_user_access')->where('company_id', $companyId)->where('user_id', $id)->exists() ? [$id] : [];
    }
}
