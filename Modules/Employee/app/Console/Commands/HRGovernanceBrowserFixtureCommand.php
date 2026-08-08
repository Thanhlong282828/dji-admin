<?php

namespace Modules\Employee\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\HRDataGovernanceService;
use Spatie\Permission\Models\Permission;

final class HRGovernanceBrowserFixtureCommand extends Command
{
    protected $signature = 'hr:governance-browser-fixture {--cleanup}';

    protected $description = 'Create or clean the guarded HR Governance browser fixture.';

    public function handle(ConnectionInterface $connection, HRDataGovernanceService $service): int
    {
        $database = (string) $connection->getDatabaseName();
        if (! app()->environment(['local', 'testing']) || ! str_ends_with($database, '_testing')) {
            $this->error('Fixture chỉ được chạy local/testing trên database *_testing.');

            return self::FAILURE;
        }
        $company = Company::query()->where('code', 'HR-GOV-FIXTURE')->first();
        if ((bool) $this->option('cleanup')) {
            if ($company !== null) {
                $runIds = DB::table('hr_data_quality_runs')->where('company_id', $company->id)->pluck('id');
                DB::table('hr_data_quality_issues')->whereIn('run_id', $runIds)->delete();
                DB::table('hr_governance_audit_events')->where('company_id', $company->id)->delete();
                DB::table('hr_data_quality_runs')->where('company_id', $company->id)->delete();
                DB::table('employees')->where('company_id', $company->id)->delete();
                DB::table('company_user_access')->where('company_id', $company->id)->delete();
                DB::table('companies')->where('id', $company->id)->delete();
            }
            User::query()->where('email', 'governance.fixture@schema.test')->delete();
            $this->info('HR Governance fixture cleaned.');

            return self::SUCCESS;
        }
        $company ??= Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'HR-GOV-FIXTURE', 'name' => 'Schema HR Governance Fixture', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::query()->updateOrCreate(['email' => 'governance.fixture@schema.test'], ['name' => 'HR Governance Reviewer', 'password' => Hash::make('123456'), 'email_verified_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $permissionNames = ['hr.governance.view', 'hr.governance.manage', 'hr.quality.run', 'hr.audit.view', 'hr.document.manage'];
        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }
        $user->givePermissionTo(Permission::whereIn('name', $permissionNames)->get());
        DB::table('company_user_access')->insertOrIgnore(['company_id' => $company->id, 'user_id' => $user->id]);
        // The in-app browser may already hold a local testing session. Make the
        // guarded fixture visible to every testing user without touching production.
        foreach (User::query()->get() as $testingUser) {
            $testingUser->givePermissionTo(Permission::whereIn('name', $permissionNames)->get());
            DB::table('company_user_access')->insertOrIgnore(['company_id' => $company->id, 'user_id' => $testingUser->id]);
        }
        Employee::query()->firstOrCreate(['company_id' => $company->id, 'employee_code' => 'GOV-001'], ['full_name' => 'Nguyễn An Governance', 'nationality' => 'VN', 'join_date' => '2026-08-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $service->run((int) $company->id, (int) $user->id, 'browser-governance-run', now()->toDateString());
        $this->line('Login: governance.fixture@schema.test / 123456');
        $this->line(url('/admin/hr/governance?company_id='.$company->id));

        return self::SUCCESS;
    }
}
