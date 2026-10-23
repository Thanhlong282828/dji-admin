<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Depends;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PermissionCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_cache_can_reference_a_transactional_permission(): int
    {
        $permission = Permission::findOrCreate('testing.permission-cache-isolation', 'web');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $permissionId = $permission->getKey();

        self::assertIsInt($permissionId);

        $user->givePermissionTo($permission);

        self::assertTrue($user->can($permission->name));
        $this->assertDatabaseHas('model_has_permissions', [
            'permission_id' => $permissionId,
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);

        return $permissionId;
    }

    #[Depends('test_permission_cache_can_reference_a_transactional_permission')]
    public function test_next_database_transaction_resolves_a_current_permission_id(int $rolledBackPermissionId): void
    {
        $this->assertDatabaseMissing('permissions', [
            'id' => $rolledBackPermissionId,
        ]);

        $permission = Permission::findOrCreate('testing.permission-cache-isolation', 'web');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $permissionId = $permission->getKey();

        self::assertIsInt($permissionId);
        self::assertNotSame($rolledBackPermissionId, $permissionId);

        $user->givePermissionTo($permission);

        $this->assertDatabaseHas('model_has_permissions', [
            'permission_id' => $permissionId,
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);

        $freshUser = $user->fresh();

        self::assertInstanceOf(User::class, $freshUser);
        self::assertTrue($freshUser->can($permission->name));
    }
}
