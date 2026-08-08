<?php

namespace Modules\Iam\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class IamBrowserFixtureCommand extends Command
{
    protected $signature = 'iam:browser-fixture {--cleanup}';

    protected $description = 'Create or remove guarded IAM browser evidence';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->error('IAM fixture is restricted to local/testing *_testing databases.');

            return self::FAILURE;
        }if ($this->option('cleanup')) {
            $this->cleanup();
            $this->info('IAM fixture removed.');

            return self::SUCCESS;
        }$this->cleanup();
        $c = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'IAM-FIXTURE', 'name' => 'Schema IAM Browser Fixture', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::query()->create(['name' => 'IAM Browser Admin', 'email' => 'iam.browser@schema.test', 'password' => Hash::make('fixture-only'), 'email_verified_at' => now()]);
        $subject = User::query()->create(['name' => 'Governed User', 'email' => 'iam.subject@schema.test', 'password' => Hash::make('fixture-only'), 'email_verified_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $subject->forceFill(['email_verified_at' => now()])->save();
        foreach (['iam.view', 'iam.request.create', 'iam.request.review', 'iam.request.approve', 'iam.assignment.manage', 'iam.privileged.manage', 'iam.sod.manage', 'iam.review.manage', 'iam.audit.view'] as $name) {
            Permission::findOrCreate($name);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo(Permission::where('name', 'like', 'iam.%')->get());
        DB::table('company_user_access')->insert([['company_id' => $c->id, 'user_id' => $user->id], ['company_id' => $c->id, 'user_id' => $subject->id]]);
        $role = Role::findOrCreate('IAM Fixture Privileged');
        DB::table('iam_privileged_roles')->insert(['company_id' => $c->id, 'role_id' => $role->id, 'risk_level' => 'privileged', 'requires_two_step_approval' => true, 'max_duration_hours' => 8, 'checksum' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('iam_access_requests')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $c->id, 'requester_id' => $user->id, 'subject_user_id' => $subject->id, 'status' => 'reviewed', 'reason' => 'Browser verification only', 'idempotency_key' => (string) Str::uuid(), 'version' => 2, 'checksum' => str_repeat('b', 64), 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('iam_sod_rules')->insert(['company_id' => $c->id, 'code' => 'IAM-FIXTURE-SOD', 'name' => 'Requester versus approver', 'left_type' => 'permission', 'left_id' => Permission::findByName('iam.request.create')->id, 'right_type' => 'permission', 'right_id' => Permission::findByName('iam.request.approve')->id, 'severity' => 'blocking', 'active' => true, 'checksum' => str_repeat('c', 64), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('iam_access_reviews')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $c->id, 'name' => 'Quarterly Privileged Review', 'scope_type' => 'company', 'status' => 'active', 'due_date' => now()->addMonth()->toDateString(), 'version' => 1, 'snapshot_checksum' => str_repeat('d', 64), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->info("company_id={$c->id}; user_id={$user->id}");

        return self::SUCCESS;
    }

    private function cleanup(): void
    {
        $c = Company::withTrashed()->where('code', 'IAM-FIXTURE')->first();
        if (! $c) {
            return;
        }$ids = DB::table('company_user_access')->where('company_id', $c->id)->pluck('user_id');
        $requests = DB::table('iam_access_requests')->where('company_id', $c->id)->pluck('id');
        $reviews = DB::table('iam_access_reviews')->where('company_id', $c->id)->pluck('id');
        DB::table('iam_access_review_items')->whereIn('access_review_id', $reviews)->delete();
        DB::table('iam_access_request_items')->whereIn('access_request_id', $requests)->delete();
        foreach (['iam_outbox', 'iam_governance_histories', 'iam_certifications', 'iam_access_reviews', 'iam_sod_violations', 'iam_sod_rules', 'iam_access_assignments', 'iam_access_requests', 'iam_privileged_roles', 'iam_role_entitlements'] as $t) {
            DB::table($t)->where('company_id', $c->id)->delete();
        }DB::table('company_user_access')->where('company_id', $c->id)->delete();
        User::query()->whereIn('id', $ids)->delete();
        $c->forceDelete();
    }
}
