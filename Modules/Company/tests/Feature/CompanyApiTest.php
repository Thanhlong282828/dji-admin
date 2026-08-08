<?php

namespace Modules\Company\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class CompanyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_create_company(): void
    {
        $this->postJson('/api/v1/companies', [])->assertUnauthorized();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/companies', [])->assertForbidden();
    }

    public function test_authorized_user_creates_company_through_cqrs(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('company.create');

        $response = $this->actingAs($user)->postJson('/api/v1/companies', ['code' => 'SCHEMA-VN', 'name' => 'Schema Vietnam', 'tax_code' => '0312345678', 'country_code' => 'VN', 'currency_code' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active']);

        $response->assertCreated()->assertJsonPath('data.code', 'SCHEMA-VN');
        $this->assertDatabaseHas(Company::class, ['code' => 'SCHEMA-VN']);
    }

    public function test_company_validation_rejects_duplicate_code(): void
    {
        Company::query()->create(['code' => 'DUPLICATE', 'name' => 'One', 'country_code' => 'VN', 'currency_code' => 'VND', 'currency' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        $user->givePermissionTo('company.create');

        $this->actingAs($user)->postJson('/api/v1/companies', ['code' => 'DUPLICATE', 'name' => 'Two', 'country_code' => 'VN', 'currency_code' => 'VND', 'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('code');
    }
}
