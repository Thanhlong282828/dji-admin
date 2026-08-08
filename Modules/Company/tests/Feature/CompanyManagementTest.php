<?php

namespace Modules\Company\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Company\Domain\Events\CompanyCreated;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorization_index_search_filter_and_pagination(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/companies')->assertForbidden();
        $user = $this->authorized('company.view');
        $this->company(['code' => 'ACTIVE-ONE', 'name' => 'Schema Active', 'status' => 'active']);
        $this->company(['code' => 'INACTIVE-ONE', 'name' => 'Schema Inactive', 'status' => 'inactive']);
        $this->actingAs($user)->get('/admin/companies?search=Active&status=active&per_page=5')->assertOk()->assertSee('ACTIVE-ONE')->assertDontSee('INACTIVE-ONE');
    }

    public function test_create_validates_unique_fields_and_dispatches_event(): void
    {
        Event::fake([CompanyCreated::class]);
        $user = $this->authorized('company.create');
        $response = $this->actingAs($user)->post('/admin/companies', $this->payload());
        $response->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseHas('companies', ['code' => 'SCHEMA-HOLDING', 'created_by' => $user->id]);
        Event::assertDispatched(CompanyCreated::class);
        $this->actingAs($user)->from('/admin/companies/create')->post('/admin/companies', $this->payload())->assertSessionHasErrors(['code', 'tax_code']);
    }

    public function test_update_status_soft_delete_and_restore(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['company.update', 'company.change-status', 'company.delete', 'company.restore', 'company.view']);
        $company = $this->company();
        $this->actingAs($user)->put("/admin/companies/{$company->id}", $this->payload(['name' => 'Schema Holding Updated']))->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Schema Holding Updated']);
        $this->actingAs($user)->patch("/admin/companies/{$company->id}/status", ['status' => 'inactive'])->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'status' => 'inactive']);
        $this->actingAs($user)->delete("/admin/companies/{$company->id}")->assertRedirect();
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->actingAs($user)->post("/admin/companies/{$company->id}/restore")->assertRedirect();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'deleted_at' => null]);
    }

    private function authorized(string $permission): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    /** @param array<string,mixed> $override */
    private function payload(array $override = []): array
    {
        return [...['code' => 'SCHEMA-HOLDING', 'name' => 'Schema Enterprise Holding', 'short_name' => 'Schema Holding', 'tax_code' => 'DEMO-0319999999', 'email' => 'holding@schema.demo', 'phone' => '+84 28 7300 1999', 'website' => 'https://example.com/schema-holding', 'address' => 'Thành phố Hồ Chí Minh', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'legal_representative' => 'Phạm Minh Anh', 'status' => 'active', 'description' => 'Dữ liệu kiểm thử Company CQRS.'], ...$override];
    }

    /** @param array<string,mixed> $override */
    private function company(array $override = []): Company
    {
        $code = (string) ($override['code'] ?? 'SCHEMA-HOLDING');

        return Company::query()->create([...$this->payload(), 'tax_code' => 'DEMO-'.hash('crc32b', $code), 'currency' => 'VND', 'is_active' => ($override['status'] ?? 'active') === 'active', ...$override]);
    }
}
