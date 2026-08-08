<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Services\GovernmentTaxFilingService;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class GovernmentFilingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_local_filing_flow_uses_immutable_snapshot_and_secure_xml(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $maker = $fixture['maker'];
        $checker = $fixture['checker'];
        $submitter = $fixture['submitter'];
        $company = $fixture['company'];
        $annual = $fixture['annual'];

        $response = $this->actingAs($maker)->post(route('admin.payroll.government-filings.store'), ['company_id' => $company, 'reconciliation_id' => $annual, 'filing_type' => 'annual_pit_employer_declaration', 'idempotency_key' => (string) Str::uuid()]);
        $response->assertRedirect();
        $filing = $this->integer(DB::table('government_tax_filings')->value('id'));
        DB::table('employees')->where('id', $fixture['employee'])->update(['full_name' => 'LIVE DATA CHANGED AFTER SNAPSHOT']);
        $this->actingAs($maker)->post($this->action($filing, $company, 'generate'), ['company_id' => $company])->assertRedirect();
        $document = DB::table('government_tax_filing_documents')->where('government_tax_filing_id', $filing)->first();
        self::assertNotNull($document);
        $path = $this->string($document->storage_path ?? null);
        $xml = Storage::disk('local')->get($path);
        self::assertIsString($xml);
        self::assertStringStartsWith('<?xml', $xml);
        self::assertStringNotContainsString('LIVE DATA CHANGED AFTER SNAPSHOT', $xml);
        self::assertSame(hash('sha256', $xml), $document->checksum);
        $this->actingAs($maker)->post($this->action($filing, $company, 'validate'), ['company_id' => $company])->assertRedirect();
        $this->actingAs($maker)->post($this->action($filing, $company, 'approve'), ['company_id' => $company])->assertSessionHasErrors('actor');
        $this->actingAs($checker)->post($this->action($filing, $company, 'approve'), ['company_id' => $company])->assertRedirect();
        $this->actingAs($checker)->post($this->action($filing, $company, 'submit'), ['company_id' => $company, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('actor');
        $this->actingAs($submitter)->post($this->action($filing, $company, 'submit'), ['company_id' => $company, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        self::assertSame('submitted', DB::table('government_tax_filings')->where('id', $filing)->value('status'));
        self::assertSame(1, DB::table('government_tax_submission_attempts')->where('government_tax_filing_id', $filing)->count());
        $download = $this->actingAs($maker)->get(route('admin.payroll.government-filings.download', ['filing' => $filing, 'document' => $document->id, 'company_id' => $company]));
        $download->assertOk()->assertHeader('content-type', 'application/xml');
        self::assertStringStartsWith('<?xml', $download->streamedContent());
        self::assertSame('success', DB::table('government_tax_access_logs')->latest('id')->value('result'));
    }

    public function test_auth_permission_company_isolation_and_invalid_transition_are_enforced(): void
    {
        $fixture = $this->fixture();
        $this->get('/admin/payroll/government-filings?company_id='.$fixture['company'])->assertRedirect('/login');
        $unprivileged = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $fixture['company'], 'user_id' => $unprivileged->id]);
        $this->actingAs($unprivileged)->get(route('admin.payroll.government-filings.index', ['company_id' => $fixture['company']]))->assertForbidden();
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.government-filings.store'), ['company_id' => $fixture['company'], 'reconciliation_id' => $fixture['annual'], 'filing_type' => 'annual_pit_employer_declaration', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $filing = $this->integer(DB::table('government_tax_filings')->value('id'));
        $foreign = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'GTF-FOREIGN', 'name' => 'Government Filing Foreign Tenant', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        DB::table('company_user_access')->insert(['company_id' => $foreign->id, 'user_id' => $fixture['maker']->id]);
        $this->actingAs($fixture['maker'])->get(route('admin.payroll.government-filings.show', ['filing' => $filing, 'company_id' => $foreign->id]))->assertNotFound();
        $this->actingAs($fixture['maker'])->post($this->action($filing, $fixture['company'], 'approve'), ['company_id' => $fixture['company']])->assertSessionHasErrors('status');
    }

    public function test_checksum_tampering_blocks_validation_and_records_issue(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.government-filings.store'), ['company_id' => $fixture['company'], 'reconciliation_id' => $fixture['annual'], 'filing_type' => 'annual_pit_employer_declaration', 'idempotency_key' => (string) Str::uuid()]);
        $filing = $this->integer(DB::table('government_tax_filings')->value('id'));
        $this->actingAs($fixture['maker'])->post($this->action($filing, $fixture['company'], 'generate'), ['company_id' => $fixture['company']]);
        DB::table('government_tax_filings')->where('id', $filing)->update(['payload_checksum' => str_repeat('0', 64)]);
        $this->actingAs($fixture['maker'])->post($this->action($filing, $fixture['company'], 'validate'), ['company_id' => $fixture['company']])->assertSessionHasErrors('filing');
        self::assertSame(1, DB::table('government_tax_validation_issues')->where('government_tax_filing_id', $filing)->where('issue_code', 'CHECKSUM_MISMATCH')->count());
    }

    public function test_wrong_lifecycle_actions_are_blocked(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $service = app(GovernmentTaxFilingService::class);
        $filing = $service->create($fixture['company'], $fixture['annual'], 'annual_pit_employer_declaration', $fixture['maker']->id, (string) Str::uuid())->filingId;
        foreach (['validate', 'approve', 'submit', 'retry', 'reject'] as $action) {
            $this->actingAs($fixture['submitter'])->post($this->action($filing, $fixture['company'], $action), ['company_id' => $fixture['company'], 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Controlled rejection reason'])->assertSessionHasErrors('status');
        }
        $service->generate($fixture['company'], $filing, $fixture['maker']->id);
        foreach (['approve', 'submit'] as $action) {
            $this->actingAs($fixture['submitter'])->post($this->action($filing, $fixture['company'], $action), ['company_id' => $fixture['company'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('status');
        }
        $service->validate($fixture['company'], $filing, $fixture['maker']->id);
        $this->actingAs($fixture['submitter'])->post($this->action($filing, $fixture['company'], 'submit'), ['company_id' => $fixture['company'], 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('status');
    }

    public function test_tampered_xml_download_is_blocked_and_access_is_logged(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.government-filings.store'), ['company_id' => $fixture['company'], 'reconciliation_id' => $fixture['annual'], 'filing_type' => 'annual_pit_employer_declaration', 'idempotency_key' => (string) Str::uuid()]);
        $filing = $this->integer(DB::table('government_tax_filings')->value('id'));
        $this->actingAs($fixture['maker'])->post($this->action($filing, $fixture['company'], 'generate'), ['company_id' => $fixture['company']]);
        $document = DB::table('government_tax_filing_documents')->where('government_tax_filing_id', $filing)->first();
        self::assertNotNull($document);
        Storage::disk('local')->put($this->string($document->storage_path ?? null), '<?xml version="1.0"?><tampered/>');

        $this->actingAs($fixture['maker'])->get(route('admin.payroll.government-filings.download', ['filing' => $filing, 'document' => $document->id, 'company_id' => $fixture['company']]))->assertSessionHasErrors('document');
        self::assertSame('blocked', DB::table('government_tax_access_logs')->latest('id')->value('result'));
    }

    #[DataProvider('permissionMatrix')]
    public function test_each_government_permission_is_enforced(string $permission, string $endpoint): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $actor = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $fixture['company'], 'user_id' => $actor->id]);
        if ($permission !== 'government.filing.view') {
            $actor->givePermissionTo('government.filing.view');
        }
        $service = app(GovernmentTaxFilingService::class);
        $filing = $service->create($fixture['company'], $fixture['annual'], 'annual_pit_employer_declaration', $fixture['maker']->id, (string) Str::uuid())->filingId;
        $documentId = 0;
        if (in_array($endpoint, ['validate', 'approve', 'submit', 'download'], true)) {
            $service->generate($fixture['company'], $filing, $fixture['maker']->id);
            $documentId = $this->integer(DB::table('government_tax_filing_documents')->where('government_tax_filing_id', $filing)->value('id'));
        }
        if (in_array($endpoint, ['approve', 'submit'], true)) {
            $service->validate($fixture['company'], $filing, $fixture['maker']->id);
        }
        if ($endpoint === 'submit') {
            $service->approve($fixture['company'], $filing, $fixture['checker']->id);
        }

        $response = match ($endpoint) {
            'view' => $this->actingAs($actor)->get(route('admin.payroll.government-filings.index', ['company_id' => $fixture['company']])),
            'create' => $this->actingAs($actor)->get(route('admin.payroll.government-filings.create', ['company_id' => $fixture['company']])),
            'download' => $this->actingAs($actor)->get(route('admin.payroll.government-filings.download', ['filing' => $filing, 'document' => $documentId, 'company_id' => $fixture['company']])),
            default => $this->actingAs($actor)->post($this->action($filing, $fixture['company'], $endpoint), ['company_id' => $fixture['company'], 'idempotency_key' => (string) Str::uuid()]),
        };

        $response->assertForbidden();
    }

    /** @return list<array{string,string}> */
    public static function permissionMatrix(): array
    {
        return [
            ['government.filing.view', 'view'],
            ['government.filing.create', 'create'],
            ['government.filing.generate', 'generate'],
            ['government.filing.validate', 'validate'],
            ['government.filing.approve', 'approve'],
            ['government.filing.submit', 'submit'],
            ['government.filing.download', 'download'],
        ];
    }

    /** @return array{company:int,annual:int,employee:int,maker:User,checker:User,submitter:User} */
    private function fixture(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'GTF-'.Str::upper(Str::random(8)), 'name' => 'Schema Government Filing Fixture', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $submitter = User::factory()->create();
        $permissions = ['government.filing.view', 'government.filing.create', 'government.filing.generate', 'government.filing.validate', 'government.filing.approve', 'government.filing.submit', 'government.filing.download'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach ([$maker, $checker, $submitter] as $user) {
            $user->givePermissionTo($permissions);
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $maker->id, 'employee_code' => 'GTF-EMP', 'full_name' => 'Immutable Taxpayer Snapshot', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);
        $checksum = hash('sha256', 'government-filing-authoritative-source');
        $annual = (int) DB::table('payroll_annual_tax_reconciliations')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'employee_id' => $employee->id, 'tax_year' => 2026, 'jurisdiction' => 'VN', 'currency' => 'VND', 'reference' => 'ATR-GTF-'.Str::upper(Str::random(8)), 'version' => 1, 'active_key' => null, 'idempotency_key' => (string) Str::uuid(), 'status' => 'finalized', 'result_type' => 'payable', 'taxpayer_snapshot' => json_encode(['employee_code' => 'GTF-EMP', 'full_name' => 'Immutable Taxpayer Snapshot', 'tax_id' => '0312345678'], JSON_THROW_ON_ERROR), 'company_tax_snapshot' => json_encode(['name' => 'Schema Government Filing Fixture', 'tax_code' => '0310000000'], JSON_THROW_ON_ERROR), 'tax_policy_snapshot' => json_encode(['code' => 'VN-PIT-2026', 'version' => 1], JSON_THROW_ON_ERROR), 'calculation_version' => 1, 'checksum_version' => 'annual-tax-source-v1', 'source_checksum' => $checksum, 'gross_income_minor' => 120_000_000, 'taxable_income_minor' => 100_000_000, 'pit_withheld_minor' => 9_000_000, 'final_tax_liability_minor' => 10_000_000, 'additional_tax_payable_minor' => 1_000_000, 'created_by' => $maker->id, 'finalized_by' => $checker->id, 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return ['company' => $company->id, 'annual' => $annual, 'employee' => $employee->id, 'maker' => $maker, 'checker' => $checker, 'submitter' => $submitter];
    }

    private function action(int $filing, int $company, string $action): string
    {
        return route('admin.payroll.government-filings.mutate', ['filing' => $filing, 'action' => $action, 'company_id' => $company]);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric ID.');
    }

    private function string(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : self::fail('Expected string.');
    }
}
