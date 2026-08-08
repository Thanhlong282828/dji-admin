<?php

namespace Modules\Notification\Infrastructure\Resolvers;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Domain\Contracts\NotificationRecipientResolverInterface;

final class ManagerRecipientResolver implements NotificationRecipientResolverInterface
{
    public function resolve(int $companyId, string $value, string $entityType, int $entityId): array
    {
        if ($entityType !== 'employee') {
            return [];
        }
        $id = DB::table('employees as e')->join('employees as m', 'm.id', '=', 'e.manager_id')->join('company_user_access as a', fn (JoinClause $join): JoinClause => $join->on('a.user_id', '=', 'm.user_id')->where('a.company_id', $companyId))->where('e.company_id', $companyId)->where('e.id', $entityId)->value('m.user_id');

        return is_numeric($id) ? [(int) $id] : [];
    }
}
