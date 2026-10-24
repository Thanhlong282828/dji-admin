<?php

namespace Tests\Feature\CustomerRefund;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class CustomerRefundHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_route_enforces_permission_and_company_scope(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $company = (int) DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'code' => 'REFUND-HTTP', 'name' => 'Refund HTTP', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Permission::findOrCreate('customer-refund.create', 'web');
        $payload = ['company_id' => $company, 'action' => 'create'];

        $this->actingAs($user)->post(route('admin.sales.customer-refunds.mutate'), $payload)->assertForbidden();
        $user->givePermissionTo('customer-refund.create');
        $this->actingAs($user)->post(route('admin.sales.customer-refunds.mutate'), $payload)->assertNotFound();
    }
}
