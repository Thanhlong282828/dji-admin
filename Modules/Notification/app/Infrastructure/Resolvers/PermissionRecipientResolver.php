<?php

namespace Modules\Notification\Infrastructure\Resolvers;

use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Domain\Contracts\NotificationRecipientResolverInterface;

final class PermissionRecipientResolver implements NotificationRecipientResolverInterface
{
    public function resolve(int $companyId, string $value, string $entityType, int $entityId): array
    {
        $values = DB::table('model_has_permissions as p')->join('permissions as x', 'x.id', '=', 'p.permission_id')->join('company_user_access as a', fn (JoinClause $join): JoinClause => $join->on('a.user_id', '=', 'p.model_id')->where('a.company_id', $companyId))->where('p.model_type', User::class)->where('x.name', $value)->distinct()->pluck('p.model_id');
        $result = [];
        foreach ($values as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $result[] = (int) $id;
            }
        }

        return $result;
    }
}
