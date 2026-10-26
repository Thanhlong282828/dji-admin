<?php

namespace Tests\Unit\Operations;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class StagingDeploymentContractTest extends TestCase
{
    public function test_staging_compose_is_isolated_and_uses_the_immutable_image_for_static_assets(): void
    {
        $compose = $this->projectFile('docker-compose.staging.yml');

        foreach (['APP_ENV: staging', 'APP_DEBUG: "false"', 'laraveldji_staging_restore', 'STAGING_PROTECTED_DATABASE_NAMES'] as $contract) {
            self::assertStringContainsString($contract, $compose);
        }
        self::assertStringContainsString('env_file: !reset []', $compose);
        self::assertStringContainsString('127.0.0.1:${APP_ORIGIN_PORT:-8081}:8080', $compose);
        self::assertStringContainsString('staging_mysql_data:/var/lib/mysql', $compose);
        self::assertStringContainsString('MYSQL_PWD="$${MYSQL_ROOT_PASSWORD}"', $compose);
        self::assertStringContainsString("--execute='SELECT 1'", $compose);
        self::assertStringNotContainsString('-proot_local_only', $compose);
        self::assertStringContainsString('staging_redis_data:/data', $compose);
        self::assertStringContainsString('staging_storage:/var/www/html/storage', $compose);
        self::assertStringContainsString('image: ${APP_IMAGE:', $compose);
        self::assertStringContainsString('cp -a /var/www/html/public/. /export/', $compose);
        self::assertStringContainsString('staging_public_assets:/var/www/html/public:ro', $compose);
        self::assertStringNotContainsString('.:/var/www/html:ro', $compose);
        self::assertStringNotContainsString('schema_admin_production', $compose);
        self::assertStringContainsString('profiles: [reverb]', $compose);
    }

    public function test_staging_preflight_is_fail_closed_and_static_only(): void
    {
        $preflight = $this->projectFile('scripts/operations/staging-preflight.sh');

        self::assertStringContainsString('tag must match RELEASE_SHA', $preflight);
        self::assertStringContainsString('STAGING_DB_DATABASE must be laraveldji_staging_restore', $preflight);
        self::assertStringContainsString('contains_database_name "$VPS_PRODUCTION_DATABASE"', $preflight);
        self::assertStringContainsString('exact protected database entry', $preflight);
        self::assertStringNotContainsString('== *"$VPS_PRODUCTION_DATABASE"*', $preflight);
        self::assertStringContainsString('Full browser acceptance requires an HTTPS staging origin', $preflight);
        self::assertStringContainsString('docker compose -f compose.yml -f docker-compose.staging.yml config --quiet', $preflight);
        self::assertDoesNotMatchRegularExpression('/^docker compose .*\s(up|pull|run|start|restart)\b/m', $preflight);
        self::assertDoesNotMatchRegularExpression('/php artisan (migrate|db:seed)\b/', $preflight);
    }

    public function test_release_and_rollback_scripts_select_staging_without_weakening_production(): void
    {
        $release = $this->projectFile('scripts/deploy/release.sh');
        $rollback = $this->projectFile('scripts/deploy/rollback.sh');

        self::assertStringContainsString('development|staging|production', $release);
        self::assertStringContainsString('docker-compose.staging.yml', $release);
        self::assertStringContainsString('docker-compose.prod.yml', $release);
        self::assertStringContainsString('php artisan operations:migration-risk', $release);
        self::assertStringContainsString('php artisan migrate --force', $release);
        self::assertStringContainsString('--environment="$environment"', $release);
        self::assertStringContainsString('predeploy_release_check+=(--allow-stale-scheduler)', $release);
        self::assertStringContainsString("canonical_repository='ghcr.io/thanhlong282828/dji-admin'", $release);
        self::assertStringContainsString('./scripts/operations/staging-preflight.sh', $release);
        $preflightPosition = strpos($release, './scripts/operations/staging-preflight.sh');
        $pullPosition = strpos($release, '"${compose[@]}" pull');
        self::assertIsInt($preflightPosition);
        self::assertIsInt($pullPosition);
        self::assertLessThan($pullPosition, $preflightPosition);
        self::assertStringContainsString('MIGRATION_COMPATIBLE', $rollback);
        self::assertStringContainsString('CONFIG_COMPATIBLE', $rollback);
        self::assertStringContainsString('DATABASE_BACKWARD_COMPATIBLE', $rollback);
        self::assertStringContainsString('docker-compose.staging.yml', $rollback);
        self::assertStringContainsString('docker-compose.prod.yml', $rollback);
        self::assertStringContainsString('environment="${1:-}"', $rollback);
        self::assertStringNotContainsString('DEPLOY_ENVIRONMENT', $rollback);
        self::assertStringContainsString('ROLLBACK_MANIFEST_PATH', $rollback);
        self::assertStringContainsString('ROLLBACK_MANIFEST_SHA256', $rollback);
        self::assertStringContainsString('independently approved SHA-256', $rollback);
        self::assertStringContainsString('manifest_snapshot_directory=', $rollback);
        self::assertStringContainsString('chmod 0700 "$manifest_snapshot_directory"', $rollback);
        self::assertStringContainsString('chmod 0400 "$manifest_snapshot"', $rollback);
        self::assertStringContainsString('hash("sha256", $contents)', $rollback);
        self::assertStringContainsString('Rollback manifest checksum mismatch', $rollback);
        self::assertStringContainsString('Rollback manifest environment mismatch', $rollback);
        self::assertStringContainsString('Rollback manifest current image mismatch', $rollback);
        self::assertStringContainsString('Rollback manifest target mismatch', $rollback);
        self::assertStringContainsString('--network none --read-only --cap-drop ALL', $rollback);
    }

    public function test_release_and_rollback_reject_foreign_image_repositories_by_contract(): void
    {
        $release = $this->projectFile('scripts/deploy/release.sh');
        $rollback = $this->projectFile('scripts/deploy/rollback.sh');

        self::assertStringContainsString('CONTAINER_IMAGE must be the canonical GHCR repository', $release);
        self::assertStringContainsString('PREVIOUS_APP_IMAGE must use the canonical repository', $release);
        self::assertStringContainsString('CURRENT_APP_IMAGE must use the canonical repository', $rollback);
        self::assertStringContainsString('PREVIOUS_APP_IMAGE must use the canonical repository', $rollback);
    }

    public function test_staging_preflight_rejects_substring_database_and_foreign_image_bypasses(): void
    {
        $environment = $this->stagingEnvironment();
        $environment['STAGING_PROTECTED_DATABASE_NAMES'] = 'not-production_database';
        $databaseBypass = new Process(['/bin/sh', base_path('scripts/operations/staging-preflight.sh')], base_path(), $environment);
        $databaseBypass->run();
        self::assertSame(65, $databaseBypass->getExitCode());
        self::assertStringContainsString('exact protected database entry', $databaseBypass->getErrorOutput());

        $environment = $this->stagingEnvironment();
        $environment['APP_IMAGE'] = 'ghcr.io/foreign/repository:'.str_repeat('a', 40).'@sha256:'.str_repeat('b', 64);
        $foreignImage = new Process(['/bin/sh', base_path('scripts/operations/staging-preflight.sh')], base_path(), $environment);
        $foreignImage->run();
        self::assertSame(64, $foreignImage->getExitCode());
        self::assertStringContainsString('approved immutable GHCR', $foreignImage->getErrorOutput());
    }

    public function test_release_check_has_a_canonical_staging_database_contract(): void
    {
        $releaseCheck = $this->projectFile('app/Console/Commands/ReleaseCheckCommand.php');

        self::assertStringContainsString("'staging' =>", $releaseCheck);
        self::assertStringContainsString("config('data-quality.staging_restore.database')", $releaseCheck);
    }

    public function test_staging_and_rollback_secrets_have_no_committed_defaults(): void
    {
        $environment = $this->projectFile('.env.example');

        foreach (['STAGING_APP_KEY', 'STAGING_DB_USERNAME', 'STAGING_DB_PASSWORD', 'STAGING_MYSQL_ROOT_PASSWORD', 'STAGING_PROTECTED_DATABASE_NAMES', 'APP_IMAGE', 'RELEASE_SHA', 'CURRENT_APP_IMAGE', 'ROLLBACK_MANIFEST_PATH', 'ROLLBACK_MANIFEST_SHA256'] as $name) {
            self::assertMatchesRegularExpression('/^'.preg_quote($name, '/').'=$/m', $environment);
        }
        self::assertStringContainsString('STAGING_DB_DATABASE=laraveldji_staging_restore', $environment);
        self::assertStringContainsString('MIGRATION_COMPATIBLE=false', $environment);
        self::assertStringContainsString('CONFIG_COMPATIBLE=false', $environment);
        self::assertStringContainsString('DATABASE_BACKWARD_COMPATIBLE=false', $environment);
    }

    public function test_rollback_negative_paths_fail_before_any_docker_action(): void
    {
        $current = 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('a', 40).'@sha256:'.str_repeat('b', 64);
        $previous = 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('c', 40).'@sha256:'.str_repeat('d', 64);
        $digestSeparator = strpos($current, '@');
        self::assertIsInt($digestSeparator);
        $basePayload = [
            'environment' => 'staging',
            'image' => ['reference' => substr($current, 0, $digestSeparator), 'digest' => substr($current, $digestSeparator + 1)],
            'rollback_target' => $previous,
        ];

        $cases = [];
        $valid = $this->writeRollbackManifest($basePayload);
        $cases['missing trusted checksum'] = [$valid['path'], '', 'staging', $current, $previous, true, true, true, null];
        $cases['production missing trusted checksum'] = [$valid['path'], '', 'production', $current, $previous, true, true, true, null];
        $cases['malformed trusted checksum'] = [$valid['path'], 'invalid', 'staging', $current, $previous, true, true, true, null];

        $original = $this->writeRollbackManifest($basePayload);
        $tamperedPayload = $basePayload;
        $tamperedPayload['rollback_target'] = 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('e', 40).'@sha256:'.str_repeat('f', 64);
        $tampered = $this->writeRollbackManifest($tamperedPayload);
        $cases['tampered manifest with recomputed internal checksum'] = [$tampered['path'], $original['sha256'], 'staging', $current, $previous, true, true, true, null];

        $crossEnvironment = $this->writeRollbackManifest([...$basePayload, 'environment' => 'production']);
        $cases['cross environment'] = [$crossEnvironment['path'], $crossEnvironment['sha256'], 'staging', $current, $previous, true, true, true, null];

        $malformedPath = tempnam(sys_get_temp_dir(), 'rollback-malformed-');
        self::assertIsString($malformedPath);
        file_put_contents($malformedPath, '{not-json');
        $cases['malformed manifest'] = [$malformedPath, hash_file('sha256', $malformedPath), 'staging', $current, $previous, true, true, true, null];

        $mismatchedCurrent = 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('1', 40).'@sha256:'.str_repeat('2', 64);
        $cases['current image mismatch'] = [$valid['path'], $valid['sha256'], 'staging', $mismatchedCurrent, $previous, true, true, true, null];
        $foreign = 'ghcr.io/foreign/repository:'.str_repeat('a', 40).'@sha256:'.str_repeat('b', 64);
        $cases['foreign repository'] = [$valid['path'], $valid['sha256'], 'staging', $foreign, $previous, true, true, true, null];
        $cases['compatibility flags false'] = [$valid['path'], $valid['sha256'], 'staging', $current, $previous, false, false, false, null];
        $cases['missing staging preflight'] = [$valid['path'], $valid['sha256'], 'staging', $current, $previous, true, true, true, 'missing'];
        $cases['failed staging preflight'] = [$valid['path'], $valid['sha256'], 'staging', $current, $previous, true, true, true, 'failure'];

        foreach ($cases as $name => [$manifestPath, $trustedChecksum, $environment, $currentImage, $previousImage, $migrationCompatible, $configCompatible, $databaseCompatible, $preflight]) {
            $result = $this->runRollbackFailure(
                $manifestPath,
                is_string($trustedChecksum) ? $trustedChecksum : '',
                $environment,
                $currentImage,
                $previousImage,
                $migrationCompatible,
                $configCompatible,
                $databaseCompatible,
                $preflight,
            );
            self::assertNotSame(0, $result['exit'], $name);
            self::assertSame('', $result['docker_log'], $name.' must not execute Docker');
        }

        foreach (array_unique(array_map(static fn (array $case): string => $case[0], $cases)) as $manifestPath) {
            unlink($manifestPath);
        }
        unlink($original['path']);
    }

    public function test_rollback_uses_verified_snapshot_after_original_manifest_changes_and_selects_staging_overlay(): void
    {
        $current = 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('a', 40).'@sha256:'.str_repeat('b', 64);
        $previous = 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('c', 40).'@sha256:'.str_repeat('d', 64);
        $digestSeparator = strpos($current, '@');
        self::assertIsInt($digestSeparator);
        $manifest = $this->writeRollbackManifest([
            'environment' => 'staging',
            'image' => ['reference' => substr($current, 0, $digestSeparator), 'digest' => substr($current, $digestSeparator + 1)],
            'rollback_target' => $previous,
        ]);
        $root = sys_get_temp_dir().'/rollback-positive-'.bin2hex(random_bytes(6));
        mkdir($root.'/bin', 0700, true);
        mkdir($root.'/tmp', 0700, true);
        mkdir($root.'/scripts/operations', 0700, true);
        $events = $root.'/events.log';
        file_put_contents($root.'/bin/docker', "#!/bin/sh\nprintf 'docker %s\\n' \"\$*\" >> ".escapeshellarg($events)."\nexit 0\n");
        file_put_contents($root.'/bin/curl', "#!/bin/sh\nprintf 'curl %s\\n' \"\$*\" >> ".escapeshellarg($events)."\nexit 0\n");
        file_put_contents($root.'/scripts/operations/staging-preflight.sh', "#!/bin/sh\nprintf 'preflight\\n' >> ".escapeshellarg($events)."\nprintf '%s' 'changed-after-snapshot' > ".escapeshellarg($manifest['path'])."\nexit 0\n");
        chmod($root.'/bin/docker', 0700);
        chmod($root.'/bin/curl', 0700);
        chmod($root.'/scripts/operations/staging-preflight.sh', 0700);

        $process = new Process(['/bin/bash', base_path('scripts/deploy/rollback.sh'), 'staging'], base_path(), [
            'PATH' => $root.'/bin:'.(getenv('PATH') ?: '/usr/bin:/bin'),
            'TMPDIR' => $root.'/tmp',
            'DEPLOY_PATH' => $root,
            'PREVIOUS_APP_IMAGE' => $previous,
            'CURRENT_APP_IMAGE' => $current,
            'ROLLBACK_MANIFEST_PATH' => $manifest['path'],
            'ROLLBACK_MANIFEST_SHA256' => $manifest['sha256'],
            'DEPLOY_HEALTH_URL' => 'https://staging.example.invalid',
            'MIGRATION_COMPATIBLE' => 'true',
            'CONFIG_COMPATIBLE' => 'true',
            'DATABASE_BACKWARD_COMPATIBLE' => 'true',
        ]);
        $process->run();
        $log = (string) file_get_contents($events);

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertMatchesRegularExpression('/^preflight\ndocker run /', $log);
        self::assertStringContainsString('docker compose -f compose.yml -f docker-compose.staging.yml up -d --remove-orphans', $log);
        self::assertStringNotContainsString($manifest['path'].':/manifest.json', $log);
        self::assertStringContainsString('/laraveldji-rollback-manifest.', $log);
        self::assertSame('changed-after-snapshot', file_get_contents($manifest['path']));

        $this->removeRollbackFixture($root);
        unlink($manifest['path']);
    }

    public function test_staging_release_fails_on_pending_migrations_without_migrating_or_starting_services(): void
    {
        $root = sys_get_temp_dir().'/release-pending-'.bin2hex(random_bytes(6));
        mkdir($root.'/bin', 0700, true);
        mkdir($root.'/scripts/operations', 0700, true);
        $events = $root.'/events.log';
        file_put_contents($root.'/bin/docker', "#!/bin/sh\nprintf 'docker %s\\n' \"\$*\" >> ".escapeshellarg($events)."\ncase \"\$*\" in *operations:release-check*) exit 65 ;; *) exit 0 ;; esac\n");
        file_put_contents($root.'/scripts/operations/staging-preflight.sh', "#!/bin/sh\nprintf 'preflight\\n' >> ".escapeshellarg($events)."\nexit 0\n");
        chmod($root.'/bin/docker', 0700);
        chmod($root.'/scripts/operations/staging-preflight.sh', 0700);
        $sha = str_repeat('a', 40);
        $previous = 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('c', 40).'@sha256:'.str_repeat('d', 64);
        $process = new Process(['/bin/bash', base_path('scripts/deploy/release.sh'), 'staging', $sha], base_path(), [
            'PATH' => $root.'/bin:'.(getenv('PATH') ?: '/usr/bin:/bin'),
            'DEPLOY_PATH' => $root,
            'CONTAINER_IMAGE' => 'ghcr.io/thanhlong282828/dji-admin',
            'IMAGE_DIGEST' => 'sha256:'.str_repeat('b', 64),
            'PREVIOUS_APP_IMAGE' => $previous,
            'QUALITY_EVIDENCE_REFERENCE' => 'ci://test',
            'BACKUP_REFERENCE' => 'backup://test',
            'RELEASE_TAG' => 'staging-test',
            'APPROVAL_REFERENCE' => 'approval://test',
            'SOURCE_CHECKSUM' => str_repeat('e', 64),
        ]);
        $process->run();
        $log = (string) file_get_contents($events);

        self::assertSame(65, $process->getExitCode());
        self::assertMatchesRegularExpression('/^preflight\ndocker compose .* config --quiet\ndocker compose .* pull\ndocker compose .* operations:migration-risk\ndocker compose .* operations:release-check/s', $log);
        self::assertStringNotContainsString('--allow-reviewed-pending', $log);
        self::assertStringNotContainsString('php artisan migrate --force', $log);
        self::assertStringNotContainsString(' up -d ', $log);

        unlink($events);
        unlink($root.'/bin/docker');
        unlink($root.'/scripts/operations/staging-preflight.sh');
        rmdir($root.'/bin');
        rmdir($root.'/scripts/operations');
        rmdir($root.'/scripts');
        rmdir($root);
    }

    /** @return array<string, string> */
    private function stagingEnvironment(): array
    {
        return [
            'APP_IMAGE' => 'ghcr.io/thanhlong282828/dji-admin:'.str_repeat('a', 40).'@sha256:'.str_repeat('b', 64),
            'RELEASE_SHA' => str_repeat('a', 40),
            'STAGING_APP_URL' => 'https://staging.example.invalid',
            'STAGING_APP_KEY' => 'test-only-empty-contract',
            'STAGING_DB_DATABASE' => 'laraveldji_staging_restore',
            'STAGING_DB_USERNAME' => 'staging_test',
            'STAGING_DB_PASSWORD' => 'test-only',
            'STAGING_MYSQL_ROOT_PASSWORD' => 'test-only',
            'STAGING_PROTECTED_DATABASE_NAMES' => 'production_database',
            'VPS_PRODUCTION_DATABASE' => 'production_database',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{path:string,sha256:string}
     */
    private function writeRollbackManifest(array $payload): array
    {
        $payload['checksum'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $path = tempnam(sys_get_temp_dir(), 'rollback-manifest-');
        self::assertIsString($path);
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $sha256 = hash_file('sha256', $path);
        self::assertIsString($sha256);

        return ['path' => $path, 'sha256' => $sha256];
    }

    /** @return array{exit:int,docker_log:string} */
    private function runRollbackFailure(string $manifestPath, string $trustedChecksum, string $environment, string $currentImage, string $previousImage, bool $migrationCompatible, bool $configCompatible, bool $databaseCompatible, ?string $preflight): array
    {
        $root = sys_get_temp_dir().'/rollback-contract-'.bin2hex(random_bytes(6));
        $bin = $root.'/bin';
        mkdir($bin, 0700, true);
        $dockerLog = $root.'/docker.log';
        file_put_contents($bin.'/docker', "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> ".escapeshellarg($dockerLog)."\nexit 99\n");
        chmod($bin.'/docker', 0700);
        if ($preflight !== 'missing') {
            mkdir($root.'/scripts/operations', 0700, true);
            file_put_contents($root.'/scripts/operations/staging-preflight.sh', $preflight === 'failure' ? "#!/bin/sh\nexit 65\n" : "#!/bin/sh\nexit 0\n");
            chmod($root.'/scripts/operations/staging-preflight.sh', 0700);
        }
        $process = new Process(['/bin/bash', base_path('scripts/deploy/rollback.sh'), $environment], base_path(), [
            'PATH' => $bin.':'.(getenv('PATH') ?: '/usr/bin:/bin'),
            'DEPLOY_PATH' => $root,
            'PREVIOUS_APP_IMAGE' => $previousImage,
            'CURRENT_APP_IMAGE' => $currentImage,
            'ROLLBACK_MANIFEST_PATH' => $manifestPath,
            'ROLLBACK_MANIFEST_SHA256' => $trustedChecksum,
            'DEPLOY_HEALTH_URL' => 'https://staging.example.invalid',
            'MIGRATION_COMPATIBLE' => $migrationCompatible ? 'true' : 'false',
            'CONFIG_COMPATIBLE' => $configCompatible ? 'true' : 'false',
            'DATABASE_BACKWARD_COMPATIBLE' => $databaseCompatible ? 'true' : 'false',
        ]);
        $process->run();

        $result = ['exit' => $process->getExitCode() ?? 255, 'docker_log' => is_file($dockerLog) ? (string) file_get_contents($dockerLog) : ''];
        if (is_file($dockerLog)) {
            unlink($dockerLog);
        }
        if (is_file($bin.'/docker')) {
            unlink($bin.'/docker');
        }
        if (is_file($root.'/scripts/operations/staging-preflight.sh')) {
            unlink($root.'/scripts/operations/staging-preflight.sh');
            rmdir($root.'/scripts/operations');
            rmdir($root.'/scripts');
        }
        rmdir($bin);
        rmdir($root);

        return $result;
    }

    private function removeRollbackFixture(string $root): void
    {
        foreach ([$root.'/events.log', $root.'/bin/docker', $root.'/bin/curl', $root.'/scripts/operations/staging-preflight.sh'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($root.'/bin');
        rmdir($root.'/tmp');
        rmdir($root.'/scripts/operations');
        rmdir($root.'/scripts');
        rmdir($root);
    }

    private function projectFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));
        self::assertIsString($contents);

        return $contents;
    }
}
