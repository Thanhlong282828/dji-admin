<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Document\Application\Services\DocumentStorageReconciliationService;
use Tests\TestCase;

final class DocumentStorageReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_is_tenant_scoped_bounded_dry_run_and_redacts_keys(): void
    {
        Storage::fake('local');
        Storage::fake('supabase');
        $company = $this->company('RECON-A');
        $foreign = $this->company('RECON-B');
        $actor = User::factory()->create();
        $document = $this->document($company, $actor->id, 'A');
        $foreignDocument = $this->document($foreign, $actor->id, 'B');
        $validPath = "enterprise-documents/{$company}/{$document}/valid.pdf";
        $missingPath = "enterprise-documents/{$company}/{$document}/missing.pdf";
        $foreignPath = "enterprise-documents/{$foreign}/{$foreignDocument}/foreign.pdf";
        Storage::disk('supabase')->put($validPath, 'valid');
        Storage::disk('supabase')->put($foreignPath, 'foreign');
        Storage::disk('supabase')->put("enterprise-documents/{$company}/orphan.pdf", 'orphan');
        $this->version($document, 1, $validPath, 'valid');
        $this->version($document, 2, $missingPath, 'missing');
        $this->version($foreignDocument, 1, $foreignPath, 'foreign');

        $result = app(DocumentStorageReconciliationService::class)->audit($company, 20, 10);
        $types = array_column($result['anomalies'], 'type');

        self::assertContains('missing_object', $types);
        self::assertContains('orphan_object', $types);
        self::assertStringNotContainsString($foreignPath, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($validPath, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertTrue(Storage::disk('supabase')->exists("enterprise-documents/{$company}/orphan.pdf"));
    }

    public function test_command_requires_company_and_reports_anomalies_without_deleting(): void
    {
        Storage::fake('local');
        Storage::fake('supabase');
        self::assertSame(2, Artisan::call('documents:storage-reconcile'));
        $company = $this->company('RECON-C');
        Storage::disk('supabase')->put("enterprise-documents/{$company}/orphan.pdf", 'orphan');

        self::assertSame(1, Artisan::call('documents:storage-reconcile', ['--company-id' => $company, '--limit' => 10, '--max-seconds' => 5]));
        self::assertStringContainsString('orphan_object', Artisan::output());
        self::assertTrue(Storage::disk('supabase')->exists("enterprise-documents/{$company}/orphan.pdf"));
    }

    public function test_active_company_coordinator_uses_bounded_cursor_batches_without_manual_id_list(): void
    {
        Storage::fake('local');
        Storage::fake('supabase');
        Cache::forget('documents:storage-reconciliation:active-company-cursor');
        foreach (range(1, 30) as $number) {
            $this->company('RECON-BATCH-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT));
        }
        DB::table('companies')->where('code', 'RECON-BATCH-30')->update(['status' => 'inactive', 'is_active' => false]);
        config()->set([
            'document.reconciliation.companies_per_run' => 7,
            'document.reconciliation.max_items' => 5,
            'document.reconciliation.max_seconds' => 2,
            'document.reconciliation.batch_max_seconds' => 60,
        ]);

        self::assertSame(0, Artisan::call('documents:storage-reconcile-tenants'));
        $first = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(7, $first['processed_companies']);
        self::assertSame(0, $first['failed_companies']);
        self::assertArrayNotHasKey('company_ids', $first);
        $firstCursor = (int) Cache::get('documents:storage-reconciliation:active-company-cursor');

        self::assertSame(0, Artisan::call('documents:storage-reconcile-tenants'));
        $second = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(7, $second['processed_companies']);
        self::assertGreaterThan($firstCursor, (int) Cache::get('documents:storage-reconciliation:active-company-cursor'));
    }

    private function company(string $code): int
    {
        return (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function document(int $companyId, int $actorId, string $suffix): int
    {
        return (int) DB::table('enterprise_documents')->insertGetId(['company_id' => $companyId, 'document_type' => 'IDENTITY_DOCUMENT', 'title' => 'Recon '.$suffix, 'classification' => 'RESTRICTED', 'owner_module' => 'Employee', 'subject_type' => 'EMPLOYEE', 'subject_id' => 1, 'status' => 'ACTIVE', 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function version(int $documentId, int $version, string $path, string $contents): void
    {
        DB::table('enterprise_document_versions')->insert(['document_id' => $documentId, 'version_number' => $version, 'storage_driver' => 'supabase', 'storage_path' => $path, 'original_name' => 'recon.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => strlen($contents), 'sha256' => hash('sha256', $contents), 'uploaded_by' => User::query()->valueOrFail('id'), 'created_at' => now(), 'updated_at' => now()]);
    }
}
