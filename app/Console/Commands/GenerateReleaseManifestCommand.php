<?php

namespace App\Console\Commands;

use App\Support\DataProtection\DataProtectionAuditService;
use App\Support\Release\ReleaseManifestData;
use App\Support\Release\ReleaseManifestGenerator;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use App\Support\Secrets\SecretGovernanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GenerateReleaseManifestCommand extends Command
{
    protected $signature = 'operations:release-manifest
        {--release-id= : Stable release identifier}
        {--commit= : Full Git commit SHA}
        {--tag= : Git release tag}
        {--source-state=committed : committed|working_tree}
        {--source-checksum= : Deterministic source-state SHA-256}
        {--environment=testing : development|testing|production}
        {--image= : Immutable image tag}
        {--digest= : sha256 image digest}
        {--rollback-target= : Previous immutable image}
        {--approval-reference=not-required-single-operator : Approval/change reference}
        {--quality-evidence= : JSON file containing gate statuses}
        {--created-at= : ISO-8601 release creation time}
        {--output= : Manifest output path}
        {--print-manifest : Print deterministic manifest JSON without writing a file}';

    protected $description = 'Generate an immutable, secret-free release manifest without deploying';

    public function handle(ReleaseManifestGenerator $generator, RuntimeConfigurationService $runtimeConfiguration, SecretGovernanceService $secrets, DataProtectionAuditService $dataProtection): int
    {
        try {
            $quality = $this->qualityEvidence($this->optionString('quality-evidence'));
            $runtimeSchemaVersion = config('runtime-configuration.schema_version', 1);
            $secretSchemaVersion = config('secret-governance.schema_version', 1);
            $secretReadiness = $secrets->readiness();
            $dataProtectionAudit = $dataProtection->audit();
            $data = new ReleaseManifestData(
                $this->optionString('release-id'),
                $this->optionString('commit'),
                $this->optionString('tag'),
                $this->optionString('source-state', 'committed'),
                $this->optionString('source-checksum'),
                $this->optionString('image'),
                $this->optionString('digest'),
                $this->optionString('created-at', now()->toIso8601String()),
                $this->optionString('environment', 'testing'),
                (int) DB::table('migrations')->count(),
                $this->migrationInventoryChecksum(),
                $this->applicationVersion(),
                PHP_VERSION,
                app()->version(),
                $quality,
                $this->optionString('approval-reference'),
                $this->optionString('rollback-target'),
                is_int($runtimeSchemaVersion) ? $runtimeSchemaVersion : 1,
                $runtimeConfiguration->snapshotChecksum(),
                'clean',
                is_int($secretSchemaVersion) ? $secretSchemaVersion : 1,
                $secretReadiness['checksum'],
                $secretReadiness['ready'] ? 'ready' : 'missing',
                $dataProtectionAudit['schema_version'],
                $dataProtectionAudit['checksum'],
                $dataProtectionAudit['status'],
            );
            $manifest = $generator->generate($data);
            if ($this->option('print-manifest')) {
                $this->line(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }
            $path = $this->optionString('output', rtrim($this->manifestDirectory(), '/').'/'.$data->releaseId.'.json');
            $generator->writeImmutable($manifest, $path);
            $this->line(json_encode(['status' => 'pass', 'path' => $path, 'release_id' => $data->releaseId, 'checksum' => $manifest['checksum']], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string, string> */
    private function qualityEvidence(string $path): array
    {
        if ($path === '-') {
            $contents = file_get_contents('php://stdin');
        } elseif ($path !== '' && is_file($path)) {
            $contents = file_get_contents($path);
        } else {
            throw new RuntimeException('Quality evidence JSON file is required.');
        }
        $decoded = json_decode(is_string($contents) ? $contents : '', true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Quality evidence must be a JSON object.');
        }
        $quality = [];
        foreach ($decoded as $gate => $status) {
            if (is_string($gate) && is_string($status)) {
                $quality[$gate] = $status;
            }
        }
        $required = config('release.required_quality_gates');
        foreach (is_array($required) ? $required : [] as $gate) {
            if (is_string($gate) && ! array_key_exists($gate, $quality)) {
                throw new RuntimeException('Missing quality gate: '.$gate);
            }
        }

        return $quality;
    }

    private function optionString(string $name, string $fallback = ''): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function manifestDirectory(): string
    {
        $directory = config('release.manifest_directory');

        return is_string($directory) ? $directory : storage_path('release-manifests');
    }

    private function applicationVersion(): string
    {
        $version = config('app.version');

        return is_string($version) && $version !== '' ? $version : 'unversioned';
    }

    private function migrationInventoryChecksum(): string
    {
        $files = glob(database_path('migrations/*.php'));
        if (! is_array($files)) {
            throw new RuntimeException('Unable to read migration inventory.');
        }
        sort($files);
        $inventory = [];
        foreach ($files as $file) {
            $inventory[basename($file)] = hash_file('sha256', $file);
        }

        return hash('sha256', json_encode($inventory, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
