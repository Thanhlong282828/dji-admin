<?php

namespace Modules\Document\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;

final class StorageRuntimeReadinessCommand extends Command
{
    protected $signature = 'documents:storage-readiness {--strict-production : Require the production environment and Supabase disks}';

    protected $description = 'Read-only verification of private document storage, malware scanning and tenant-scoped reconciliation readiness.';

    public function handle(ExecutableFinder $executables): int
    {
        $strict = (bool) $this->option('strict-production');
        $binary = config('document.malware_scanner_binary', 'clamscan');
        $binaryPath = is_string($binary) && $binary !== ''
            ? (str_contains($binary, DIRECTORY_SEPARATOR) && is_executable($binary) ? $binary : $executables->find($binary))
            : null;
        $databaseDirectory = config('document.malware_scanner_database_directory', '/var/lib/clamav');
        $signatureAge = $this->newestSignatureAgeHours(is_string($databaseDirectory) ? $databaseDirectory : '');
        $maxSignatureAge = max(1, (int) config('document.malware_scanner_max_signature_age_hours', 48));
        $scheduleEnabled = (bool) config('document.reconciliation.schedule_enabled', false);
        $companiesPerRun = (int) config('document.reconciliation.companies_per_run', 25);
        $batchMaxSeconds = (int) config('document.reconciliation.batch_max_seconds', 240);

        $checks = [
            'production_environment' => ! $strict || app()->environment('production'),
            'document_disk_supabase' => ! $strict || config('document.storage_disk') === 'supabase',
            'employee_disk_supabase' => ! $strict || config('employee.document_disk') === 'supabase',
            'supabase_endpoint_present' => is_string(config('filesystems.disks.supabase.endpoint')) && config('filesystems.disks.supabase.endpoint') !== '',
            'supabase_bucket_present' => is_string(config('filesystems.disks.supabase.bucket')) && config('filesystems.disks.supabase.bucket') !== '',
            'supabase_access_key_present' => is_string(config('filesystems.disks.supabase.key')) && config('filesystems.disks.supabase.key') !== '',
            'supabase_secret_present' => is_string(config('filesystems.disks.supabase.secret')) && config('filesystems.disks.supabase.secret') !== '',
            'document_scanner_clamav' => config('document.malware_scanner') === 'clamav',
            'employee_scanner_clamav' => config('employee.malware_scanner') === 'clamav',
            'clamav_binary_executable' => is_string($binaryPath) && is_executable($binaryPath),
            'clamav_signatures_present' => $signatureAge !== null,
            'clamav_signatures_fresh' => $signatureAge !== null && $signatureAge <= $maxSignatureAge,
            'reconciliation_tenant_scoped' => ! $scheduleEnabled || ($companiesPerRun >= 1 && $companiesPerRun <= 100 && $batchMaxSeconds >= 30 && $batchMaxSeconds <= 300),
        ];

        $result = ['ready' => ! in_array(false, $checks, true), 'checks' => $checks];
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }

    private function newestSignatureAgeHours(string $directory): ?int
    {
        if ($directory === '' || ! is_dir($directory)) {
            return null;
        }

        $files = array_merge(glob($directory.'/*.cvd') ?: [], glob($directory.'/*.cld') ?: []);
        $modified = array_filter(array_map(static fn (string $file): int|false => filemtime($file), $files), static fn (int|false $value): bool => is_int($value));
        if ($modified === []) {
            return null;
        }

        return (int) floor((time() - max($modified)) / 3600);
    }
}
