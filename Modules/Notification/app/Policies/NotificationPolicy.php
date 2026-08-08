<?php

namespace Modules\Notification\Policies;

use App\Models\User;
use Modules\Organization\Domain\Services\TenantAccessService;

final readonly class NotificationPolicy
{
    public function __construct(private TenantAccessService $tenant) {}

    public function authorize(User $user, int $companyId, string $permission): void
    {
        abort_unless($this->tenant->canAccess($user, $companyId), 404);
        abort_unless($user->can($permission), 403);
    }
}
