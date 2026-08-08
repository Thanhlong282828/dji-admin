<?php

namespace Modules\Audit\Domain\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;

final readonly class AuditTenantAccessService
{
    public function __construct(private ConnectionInterface $db) {}

    public function authorize(User $user, int $company): void
    {
        abort_unless($user->can('organization.tenant-all') || $this->db->table('company_user_access')->where(['company_id' => $company, 'user_id' => $user->id])->exists(), 404);
    }
}
