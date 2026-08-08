<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Application\Commands\CreateAnnualTaxReconciliation\CreateAnnualTaxReconciliationCommand;
use Modules\Payroll\Application\Queries\GetAnnualTaxReconciliation\GetAnnualTaxReconciliationQuery;
use Modules\Payroll\Domain\Services\PayrollAnnualTaxReconciliationService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PayrollAnnualTaxSecurityPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_mutation_route_has_its_own_permission_middleware(): void
    {
        /** @var array<string, Route> $routes */
        $routes = [];
        foreach (app('router')->getRoutes()->getRoutes() as $registeredRoute) {
            $name = $registeredRoute->getName();
            if ($name !== null) {
                $routes[$name] = $registeredRoute;
            }
        }
        foreach (['collect' => 'calculate', 'calculate' => 'calculate', 'validate' => 'validate', 'submit' => 'submit', 'review' => 'review', 'approve' => 'approve', 'finalize' => 'finalize', 'reject' => 'reject', 'supersede' => 'supersede'] as $action => $permission) {
            $route = $routes['admin.payroll.annual-tax.'.$action] ?? null;
            self::assertNotNull($route);
            self::assertContains('permission:payroll.annual_tax.'.$permission, $route->gatherMiddleware());
            self::assertSame(['POST'], $route->methods());
        }
        self::assertArrayNotHasKey('admin.payroll.annual-tax.mutate', $routes);
    }

    public function test_foreign_detail_is_hidden_and_missing_action_permission_is_forbidden(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['payroll.annual_tax.view', 'payroll.annual_tax.calculate'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        [$companyA,$employeeA] = $this->companyEmployee('ATSA');
        [$companyB,$employeeB] = $this->companyEmployee('ATSB');
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $owner->givePermissionTo(['payroll.annual_tax.view', 'payroll.annual_tax.calculate']);
        $viewer->givePermissionTo('payroll.annual_tax.view');
        DB::table('company_user_access')->insert([['company_id' => $companyA->id, 'user_id' => $owner->id], ['company_id' => $companyA->id, 'user_id' => $viewer->id]]);
        $draft = app(PayrollAnnualTaxReconciliationService::class)->create(new CreateAnnualTaxReconciliationCommand($companyB->id, $employeeB->id, 2026, 'VN', 'VND', (string) Str::uuid(), $owner->id));
        $this->actingAs($owner)->get(route('admin.payroll.annual-tax.show', ['reconciliation' => $draft->id, 'company_id' => $companyA->id]))->assertNotFound();
        $this->actingAs($owner)->post(route('admin.payroll.annual-tax.collect', ['reconciliation' => $draft->id]), ['company_id' => $companyA->id])->assertNotFound();
        $this->actingAs($viewer)->post(route('admin.payroll.annual-tax.collect', ['reconciliation' => $draft->id]), ['company_id' => $companyA->id])->assertForbidden();
        self::assertSame(0, DB::table('payroll_annual_tax_sources')->where('annual_tax_reconciliation_id', $draft->id)->count());
        self::assertSame($companyA->id, $employeeA->company_id);
    }

    public function test_detail_collections_are_independently_bounded(): void
    {
        [$company,$employee] = $this->companyEmployee('ATPAGE');
        $actor = User::factory()->create();
        $draft = app(PayrollAnnualTaxReconciliationService::class)->create(new CreateAnnualTaxReconciliationCommand($company->id, $employee->id, 2026, 'VN', 'VND', (string) Str::uuid(), $actor->id));
        for ($i = 0; $i < 40; $i++) {
            DB::table('payroll_annual_tax_histories')->insert(['annual_tax_reconciliation_id' => $draft->id, 'company_id' => $company->id, 'employee_id' => $employee->id, 'to_status' => 'draft', 'action' => 'audit_'.$i, 'actor_id' => $actor->id, 'created_at' => now()]);
        }
        $result = app(QueryBusInterface::class)->ask(new GetAnnualTaxReconciliationQuery($company->id, 'detail', $draft->id, ['history_page' => 1]));
        self::assertIsArray($result);
        $history = $result['history'] ?? null;
        $page = $result['history_page'] ?? null;
        self::assertIsArray($history);
        self::assertInstanceOf(LengthAwarePaginator::class, $page);
        self::assertCount(25, $history);
        self::assertSame(25, $page->perPage());
        self::assertSame(41, $page->total());
    }

    /** @return array{Company,Employee} */
    private function companyEmployee(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => $code.'-E', 'full_name' => 'Annual Tax Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $employee];
    }
}
