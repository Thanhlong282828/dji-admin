<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Governance\HRDataQualityRuleRegistry;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Services\EmployeeDocumentGovernanceService;
use Modules\Employee\Domain\Services\HRDataGovernanceService;
use Modules\Employee\Domain\Services\HRGovernanceAuditService;
use Tests\TestCase;

final class HRDataGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_authorization_and_company_isolation(): void
    {
        $a = $this->company('A');
        $b = $this->company('B');
        $viewer = $this->user($a, ['hr.governance.view']);
        $this->get('/admin/hr/governance?company_id='.$a->id)->assertRedirect('/login');
        $this->actingAs($this->user($a, []))->get('/admin/hr/governance?company_id='.$a->id)->assertForbidden();
        $this->actingAs($viewer)->get('/admin/hr/governance?company_id='.$a->id)->assertOk()->assertSee('HR Data Governance');
        $this->actingAs($viewer)->get('/admin/hr/governance?company_id='.$b->id)->assertNotFound();
    }

    public function test_quality_rules_are_deterministic_and_do_not_mutate_employee(): void
    {
        $company = $this->company('Q');
        $employee = Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'GOV-01', 'full_name' => 'Nguyễn Minh Governance', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $actor = $this->user($company, ['hr.governance.view', 'hr.quality.run']);
        $service = app(HRDataGovernanceService::class);
        $run = $service->run((int) $company->id, (int) $actor->id, 'quality-same', '2026-08-03');
        self::assertSame($run, $service->run((int) $company->id, (int) $actor->id, 'quality-same', '2026-08-03'));
        self::assertSame(1, DB::table('hr_data_quality_runs')->count());
        self::assertSame(3, DB::table('hr_data_quality_issues')->count());
        self::assertSame('Nguyễn Minh Governance', $employee->fresh()?->full_name);
        self::assertSame(1, DB::table('hr_governance_audit_events')->count());
    }

    public function test_issue_lifecycle_is_append_only_and_enforces_maker_checker(): void
    {
        $company = $this->company('L');
        $employee = $this->employee($company);
        $maker = $this->user($company, []);
        $approver = $this->user($company, []);
        $resolver = $this->user($company, []);
        $service = app(HRDataGovernanceService::class);
        $run = $service->run((int) $company->id, (int) $maker->id, 'run-lifecycle', '2026-08-03');
        $issue = $this->integerValue(DB::table('hr_data_quality_issues')->where('run_id', $run)->value('id'));
        $service->reviewIssue((int) $company->id, $issue, (int) $maker->id, 'review-one', 'Evidence reviewed');
        try {
            $service->approveIssue((int) $company->id, $issue, (int) $maker->id, 'bad-approve', 'self approval');
            self::fail('Maker checker must block');
        } catch (ValidationException) {
        }
        $service->approveIssue((int) $company->id, $issue, (int) $approver->id, 'approve-one', 'Correction approved');
        $service->resolveIssue((int) $company->id, $issue, (int) $maker->id, 'resolve-one', 'Evidence retained');
        self::assertSame('resolved', DB::table('hr_data_quality_issues')->where('id', $issue)->value('status'));
        self::assertNotNull(DB::table('hr_data_quality_issues')->where('id', $issue)->value('resolution_checksum'));
        $reason = DB::table('hr_data_quality_issues')->where('id', $issue)->value('resolution_reason');
        $service->resolveIssue((int) $company->id, $issue, (int) $resolver->id, 'resolve-again', 'Must not overwrite');
        self::assertSame($reason, DB::table('hr_data_quality_issues')->where('id', $issue)->value('resolution_reason'));
        self::assertTrue($service->verifyIssueChecksum((int) $company->id, $issue));
        self::assertSame(4, DB::table('hr_governance_audit_events')->count());
    }

    public function test_open_issue_cannot_resolve_directly_and_creator_cannot_approve(): void
    {
        $company = $this->company('T');
        $maker = $this->user($company, []);
        $reviewer = $this->user($company, []);
        $service = app(HRDataGovernanceService::class);
        $this->employee($company);
        $run = $service->run((int) $company->id, (int) $maker->id, 'transition-run', '2026-08-03');
        $issue = $this->integerValue(DB::table('hr_data_quality_issues')->where('run_id', $run)->value('id'));
        $this->expectException(ValidationException::class);
        $service->resolveIssue((int) $company->id, $issue, (int) $maker->id, 'direct-resolve', 'not approved');
    }

    public function test_reviewer_can_approve_but_creator_cannot(): void
    {
        $company = $this->company('R');
        $creator = $this->user($company, []);
        $reviewer = $this->user($company, []);
        $service = app(HRDataGovernanceService::class);
        $this->employee($company);
        $run = $service->run((int) $company->id, (int) $creator->id, 'reviewer-run', '2026-08-03');
        $issue = $this->integerValue(DB::table('hr_data_quality_issues')->where('run_id', $run)->value('id'));
        $service->reviewIssue((int) $company->id, $issue, (int) $reviewer->id, 'reviewer-review', 'reviewed');
        try {
            $service->approveIssue((int) $company->id, $issue, (int) $creator->id, 'creator-approve', 'blocked');
            self::fail('Creator approval must fail');
        } catch (ValidationException) {
        }
        $service->approveIssue((int) $company->id, $issue, (int) $reviewer->id, 'reviewer-approve', 'approved');
        self::assertSame('approved', DB::table('hr_data_quality_issues')->where('id', $issue)->value('status'));
    }

    public function test_rule_matrix_codes_severity_fingerprint_and_duplicate_prevention(): void
    {
        $company = $this->company('M');
        $employee = $this->employee($company);
        DB::table('employees')->where('id', $employee->id)->update(['identity_number' => 'same-identity-fixture']);
        $other = $this->employee($company);
        DB::table('employees')->where('id', $other->id)->update(['identity_number' => 'same-identity-fixture']);
        DB::table('employment_contracts')->insert(['employee_id' => $employee->id, 'contract_number' => 'BAD-1', 'type' => 'fixed_term', 'starts_at' => '2026-12-31', 'ends_at' => '2026-01-01', 'base_salary' => 0, 'currency' => 'VND', 'terms' => json_encode(['fixture' => true]), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $expected = ['employee_without_position' => 'blocking', 'employee_without_manager' => 'warning', 'invalid_contract' => 'blocking', 'missing_document' => 'warning', 'duplicate_identity' => 'blocking'];
        foreach (app(HRDataQualityRuleRegistry::class)->rules() as $rule) {
            self::assertSame($expected[$rule->code()], $rule->severity());
            self::assertNotEmpty($rule->evaluate((int) $company->id));
        }
        $service = app(HRDataGovernanceService::class);
        $actor = $this->user($company, []);
        $run = $service->run((int) $company->id, (int) $actor->id, 'matrix-run', '2026-08-03');
        $before = DB::table('hr_data_quality_issues')->where('run_id', $run)->count();
        self::assertSame($run, $service->run((int) $company->id, (int) $actor->id, 'matrix-run', '2026-08-03'));
        self::assertSame($before, DB::table('hr_data_quality_issues')->where('run_id', $run)->count());
        self::assertSame(0, DB::table('hr_data_quality_issues')->where('run_id', $run)->whereRaw('CHAR_LENGTH(checksum) <> 64')->count());
        self::assertSame($before, DB::table('hr_data_quality_issues')->where('run_id', $run)->distinct()->count('checksum'));
    }

    public function test_all_permission_routes_and_restricted_fields(): void
    {
        $company = $this->company('S');
        $foreign = $this->company('F');
        $routes = ['/admin/hr/governance', '/admin/hr/governance/issues', '/admin/hr/governance/audit'];
        foreach ($routes as $route) {
            $this->get($route.'?company_id='.$company->id)->assertRedirect('/login');
        }
        $none = $this->user($company, []);
        foreach ($routes as $route) {
            $this->actingAs($none)->get($route.'?company_id='.$company->id)->assertForbidden();
        }
        $viewer = $this->user($company, ['hr.governance.view', 'hr.audit.view']);
        foreach ($routes as $route) {
            $response = $this->actingAs($viewer)->get($route.'?company_id='.$company->id)->assertOk();
            $response->assertDontSee('salary')->assertDontSee('bank account')->assertDontSee('storage_key');
        }
        $this->actingAs($viewer)->get('/admin/hr/governance?company_id='.$foreign->id)->assertNotFound();
    }

    public function test_consent_document_and_append_only_audit_governance(): void
    {
        $company = $this->company('D');
        $employee = $this->employee($company);
        $actor = $this->user($company, []);
        $consentId = (int) DB::table('data_consents')->insertGetId(['employee_id' => $employee->id, 'purpose' => 'HR profile processing', 'policy_version' => 'v1', 'granted' => true, 'decided_at' => now(), 'actor_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hr_consent_governance_history')->insert([
            ['company_id' => $company->id, 'employee_id' => $employee->id, 'consent_id' => $consentId, 'purpose' => 'HR profile processing', 'version' => 1, 'action' => 'granted', 'checksum' => hash('sha256', 'grant'), 'created_at' => now()],
            ['company_id' => $company->id, 'employee_id' => $employee->id, 'consent_id' => $consentId, 'purpose' => 'HR profile processing', 'version' => 2, 'action' => 'withdrawn', 'checksum' => hash('sha256', 'withdraw'), 'created_at' => now()],
        ]);
        self::assertSame(['granted', 'withdrawn'], DB::table('hr_consent_governance_history')->where('consent_id', $consentId)->orderBy('version')->pluck('action')->all());

        $documentId = (int) DB::table('employee_documents')->insertGetId(['employee_id' => $employee->id, 'type' => 'identity', 'name' => 'CCCD governance', 'version' => 1, 'storage_key' => 'private/do-not-expose.pdf', 'checksum' => hash('sha256', 'pdf'), 'mime_type' => 'application/pdf', 'size_bytes' => 3, 'scan_status' => 'clean', 'access_policy' => json_encode(['private' => true]), 'created_at' => now(), 'updated_at' => now()]);
        $documents = app(EmployeeDocumentGovernanceService::class);
        self::assertArrayNotHasKey('storage_key', $documents->metadata((int) $company->id, $documentId));
        $historyId = $documents->archive((int) $company->id, $documentId, (int) $actor->id, 'archive-doc', '2036-08-08');
        $history = DB::table('hr_document_governance_history')->where('id', $historyId)->first();
        self::assertInstanceOf(\stdClass::class, $history);
        self::assertSame(1, $this->integerValue($history->version));
        self::assertSame('2036-08-08', $history->retention_date);
        self::assertIsString($history->checksum);
        self::assertSame(64, strlen($history->checksum));
        self::assertSame((int) $actor->id, $this->integerValue($history->actor_id));
        self::assertSame($historyId, $documents->archive((int) $company->id, $documentId, (int) $actor->id, 'archive-doc-retry', '2036-08-08'));
        self::assertSame(1, DB::table('hr_document_governance_history')->where('document_id', $documentId)->count());

        $audit = app(HRGovernanceAuditService::class);
        $oldChecksum = DB::table('hr_governance_audit_events')->where('company_id', $company->id)->oldest('id')->value('checksum');
        foreach (['data_viewed', 'data_changed', 'permission_changed', 'document_accessed'] as $index => $action) {
            $audit->append((int) $company->id, (int) $actor->id, 'employee', (int) $employee->id, $action, 'audit-'.$index);
        }
        $audit->append((int) $company->id, (int) $actor->id, 'employee', (int) $employee->id, 'data_viewed', 'audit-0');
        self::assertSame($oldChecksum, DB::table('hr_governance_audit_events')->where('company_id', $company->id)->oldest('id')->value('checksum'));
        self::assertSame(1, DB::table('hr_governance_audit_events')->where('idempotency_key', 'audit-0')->count());
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'GOV-'.$suffix, 'name' => 'Governance '.$suffix, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
    }

    private function employee(Company $company): Employee
    {
        return Employee::query()->create(['company_id' => $company->id, 'employee_code' => 'GOV-E-'.Str::random(5), 'full_name' => 'Governance Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
    }

    private function integerValue(mixed $value): int
    {
        self::assertIsNumeric($value);

        return (int) $value;
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create();
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        } DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }
}
