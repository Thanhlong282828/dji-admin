<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\DocumentStorageTelemetry;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Document\Application\Services\DocumentScanStatus as EnterpriseStatus;
use Modules\Document\Infrastructure\Security\ClamAvDocumentMalwareScanner;
use Modules\Employee\Domain\Enums\DocumentScanStatus as EmployeeStatus;
use Modules\Employee\Infrastructure\Security\ClamAvMalwareScanner;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Tests\TestCase;

final class DocumentStorageHardeningTest extends TestCase
{
    public function test_vps_readiness_requires_supabase_clamav_signatures_and_tenant_scoped_schedule(): void
    {
        $scanner = $this->script('exit 0');
        $signatures = sys_get_temp_dir().'/clamav-signatures-'.bin2hex(random_bytes(6));
        mkdir($signatures, 0700, true);
        file_put_contents($signatures.'/main.cvd', 'ClamAV-VDB:test-only-signature-metadata');

        try {
            config()->set([
                'document.storage_disk' => 'supabase',
                'employee.document_disk' => 'supabase',
                'document.malware_scanner' => 'clamav',
                'employee.malware_scanner' => 'clamav',
                'document.malware_scanner_binary' => $scanner,
                'document.malware_scanner_database_directory' => $signatures,
                'document.malware_scanner_max_signature_age_hours' => 48,
                'document.reconciliation.schedule_enabled' => true,
                'document.reconciliation.companies_per_run' => 25,
                'document.reconciliation.batch_max_seconds' => 240,
                'filesystems.disks.supabase.endpoint' => 'https://example.invalid/storage/v1/s3',
                'filesystems.disks.supabase.bucket' => 'private-test-bucket',
                'filesystems.disks.supabase.key' => 'configured-test-key',
                'filesystems.disks.supabase.secret' => 'configured-test-secret',
            ]);

            $exitCode = Artisan::call('documents:storage-readiness');
            $output = Artisan::output();
            self::assertSame(0, $exitCode, $output);
            self::assertStringContainsString('"ready":true', $output);
            self::assertStringNotContainsString('configured-test-key', $output);
            self::assertStringNotContainsString('configured-test-secret', $output);

            unlink($signatures.'/main.cvd');
            self::assertSame(1, Artisan::call('documents:storage-readiness'));
            $missingSignatureOutput = Artisan::output();
            self::assertStringContainsString('"clamav_signatures_present":false', $missingSignatureOutput);
            self::assertStringContainsString('"clamav_signature_database_loadable":false', $missingSignatureOutput);

            file_put_contents($signatures.'/main.cvd', 'ClamAV-VDB:test-only-signature-metadata');

            config()->set('document.reconciliation.companies_per_run', 0);
            self::assertSame(1, Artisan::call('documents:storage-readiness'));
            self::assertStringContainsString('"reconciliation_tenant_scoped":false', Artisan::output());
        } finally {
            @unlink($signatures.'/main.cvd');
            @rmdir($signatures);
            @unlink($scanner);
        }
    }

    public function test_clamav_adapters_classify_clean_infected_error_unavailable_and_timeout(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'document-scan-');
        self::assertIsString($file);
        file_put_contents($file, 'safe fixture');

        try {
            self::assertSame(EnterpriseStatus::Clean, $this->enterpriseScanner(0)->scan($file));
            self::assertSame(EnterpriseStatus::Infected, $this->enterpriseScanner(1)->scan($file));
            self::assertSame(EnterpriseStatus::Failed, $this->enterpriseScanner(2)->scan($file));
            self::assertSame(EmployeeStatus::Clean, $this->employeeScanner(0)->scan($file));
            self::assertSame(EmployeeStatus::Infected, $this->employeeScanner(1)->scan($file));
            self::assertSame(EmployeeStatus::Failed, $this->employeeScanner(2)->scan($file));

            config()->set('document.malware_scanner_binary', '/missing/clamscan');
            self::assertSame(EnterpriseStatus::Failed, (new ClamAvDocumentMalwareScanner)->scan($file));

            $slow = $this->script('sleep 2; exit 0');
            config()->set('employee.malware_scanner_binary', $slow);
            config()->set('employee.malware_scanner_timeout_seconds', 1);
            self::assertSame(EmployeeStatus::Failed, (new ClamAvMalwareScanner)->scan($file));
            unlink($slow);
        } finally {
            @unlink($file);
        }
    }

    public function test_storage_telemetry_records_safe_metrics_without_sensitive_error_text(): void
    {
        $logger = new class extends AbstractLogger
        {
            /** @var list<array{level:mixed,message:string,context:array<string,mixed>}> */
            public array $entries = [];

            /** @param array<string,mixed> $context */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->entries[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        $telemetry = new DocumentStorageTelemetry(app(Repository::class), $logger);
        $telemetry->record('signed_url', 'supabase', 99, false, 12.4, new RuntimeException('secret-object-key signed-url-token'));

        self::assertSame(1, Cache::get('operations:storage:signed_url:supabase:total'));
        self::assertSame(1, Cache::get('operations:storage:signed_url:supabase:failure'));
        self::assertSame(1, Cache::get('operations:storage:errors:transport_error'));
        self::assertCount(1, $logger->entries);
        self::assertSame('warning', $logger->entries[0]['level']);
        self::assertSame('Document storage operation completed.', $logger->entries[0]['message']);
        self::assertStringNotContainsString('secret-object-key', json_encode($logger->entries[0]['context'], JSON_THROW_ON_ERROR));
        $snapshot = $telemetry->snapshot();
        self::assertSame(1, $snapshot['operations']['signed_url']['failure']);
        self::assertSame(1, $snapshot['signed_url_failures']);
    }

    private function enterpriseScanner(int $exit): ClamAvDocumentMalwareScanner
    {
        config()->set('document.malware_scanner_binary', $this->script('exit '.$exit));
        config()->set('document.malware_scanner_timeout_seconds', 2);
        config()->set('document.malware_scanner_database_directory', '');

        return new ClamAvDocumentMalwareScanner;
    }

    private function employeeScanner(int $exit): ClamAvMalwareScanner
    {
        config()->set('employee.malware_scanner_binary', $this->script('exit '.$exit));
        config()->set('employee.malware_scanner_timeout_seconds', 2);
        config()->set('employee.malware_scanner_database_directory', '');

        return new ClamAvMalwareScanner;
    }

    private function script(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fake-clamscan-');
        self::assertIsString($path);
        file_put_contents($path, "#!/bin/sh\n{$body}\n");
        chmod($path, 0700);

        return $path;
    }
}
