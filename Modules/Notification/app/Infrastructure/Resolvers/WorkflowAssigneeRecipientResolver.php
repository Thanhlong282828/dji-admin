<?php

namespace Modules\Notification\Infrastructure\Resolvers;

use Illuminate\Support\Facades\DB;
use Modules\Notification\Domain\Contracts\NotificationRecipientResolverInterface;

final class WorkflowAssigneeRecipientResolver implements NotificationRecipientResolverInterface
{
    public function resolve(int $companyId, string $value, string $entityType, int $entityId): array
    {
        $values = DB::table('workflow_tasks')->where('company_id', $companyId)->where('workflow_instance_id', $entityId)->whereIn('status', ['pending', 'claimed'])->whereNotNull('assigned_user_id')->distinct()->pluck('assigned_user_id');
        $result = [];
        foreach ($values as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $result[] = (int) $id;
            }
        }

        return $result;
    }
}
