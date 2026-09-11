<?php

namespace App\Support\GoLive;

use App\Support\Secrets\SecretGovernanceService;

final class ProductionReadinessService
{
    public function __construct(private readonly SecretGovernanceService $secrets) {}

    /**
     * @return array{classification:string,summary:array{total:int,passed:int,warnings:int,blocked:int},checks:list<array{key:string,label:string,category:string,status:string,detail:string}>,dependencies:list<array{name:string,status:string,detail:string}>,blockers:list<array{type:string,dependency:string,detail:string}>,migration_count:int,generated_at:string}
     */
    public function snapshot(): array
    {
        $defaultConnection = $this->string(config('database.default'));
        $db = $defaultConnection === '' ? '' : $this->string(config('database.connections.'.$defaultConnection.'.database'));
        $appUrl = $this->string(config('app.url'));
        $productionDb = $this->string(config('release.production_database'));
        $secretReady = $this->secrets->readiness()['ready'];
        $checks = [
            $this->check('debug', 'APP_DEBUG production-safe', 'Application', ! (bool) config('app.debug'), 'Production requires APP_DEBUG=false.'),
            $this->check('url', 'Canonical HTTPS URL', 'Application', str_starts_with($appUrl, 'https://'), 'APP_URL must be the verified HTTPS production origin.'),
            $this->check('key', 'Required secret metadata', 'Secrets', $secretReady, 'Only presence/readiness metadata is inspected; values are never emitted.'),
            $this->check('database-name', 'Separate production database', 'Database', $productionDb !== '' && ! $this->reservedDatabase($productionDb), 'Production must supply VPS_PRODUCTION_DATABASE and it cannot be a local/test schema.'),
            $this->check('runtime-database', 'Current runtime database safety', 'Database', ! $this->reservedDatabase($db), 'The current process must not point production at a canonical local/test database.'),
            $this->check('cache', 'Shared cache configured', 'Cache / Redis', $this->string(config('cache.default')) === 'redis', 'Production cache should use Redis.'),
            $this->check('queue', 'Durable queue configured', 'Queue', $this->string(config('queue.default')) === 'redis', 'Production queue should use Redis/Horizon workers.'),
            $this->check('session', 'Shared encrypted session', 'Session', in_array($this->string(config('session.driver')), ['redis', 'database'], true) && (bool) config('session.encrypt'), 'Session must be shared and encrypted.'),
            $this->check('cookie', 'Secure session cookie', 'Security', (bool) config('session.secure'), 'HTTPS production requires secure cookies.'),
            $this->check('logging', 'Bounded production logging', 'Observability', $this->string(config('logging.default')) === 'production', 'Production channel writes stderr plus bounded daily logs.'),
            $this->check('assets', 'Compiled frontend manifest', 'Release', is_file(public_path('build/manifest.json')), 'Production must use compiled Vite assets.'),
            $this->check('storage', 'Runtime writable paths', 'Storage', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'Only storage and bootstrap/cache require runtime writes.'),
            $this->check('health', 'Health endpoints registered', 'Health', route('health.live', [], false) === '/health/live' && route('health.ready', [], false) === '/health/ready', 'Liveness and readiness are distinct read-only probes.'),
            $this->check('migrations', 'Migration inventory readable', 'Database', $this->migrationInventoryValid(), 'Readiness validates a non-empty, uniquely named, readable migration inventory; it never executes migrations.'),
        ];

        $dependencies = [
            $this->dependency('MySQL', $productionDb !== '' ? 'CONFIGURED' : 'BLOCKED', $productionDb !== '' ? 'Production database name configured; connectivity requires deployment environment verification.' : 'Production database is not provisioned/identified in this environment.'),
            $this->dependency('Redis', $this->string(config('cache.default')) === 'redis' ? 'CONFIGURED' : 'BLOCKED', 'Cache, queue, locks and Horizon require deployment-environment verification.'),
            $this->dependency('Mail', $this->string(config('mail.default')) === 'log' ? 'MOCK_ONLY' : 'CONFIGURED', 'No delivery claim is made without a provider smoke test.'),
            $this->dependency('Cloudflare Images', $this->cloudflareConfigured() ? 'CONFIGURED' : 'CONFIGURATION_REQUIRED', $this->cloudflareConfigured() ? 'Required configuration metadata is present; deployment health remains non-critical and must be verified without exposing credentials.' : 'Configuration is absent; local media remains the safe fallback.'),
            $this->dependency('Object storage', $this->string(config('filesystems.default')) === 's3' ? 'CONFIGURED' : 'NOT_REQUIRED', 'Private local storage remains supported; external objects need provider-specific backup.'),
            $this->dependency('Reverb', $this->string(config('broadcasting.default')) === 'reverb' ? 'CONFIGURED' : 'MOCK_ONLY', 'Realtime is non-critical and must remain fail-open for CRUD.'),
            $this->dependency('ERP', 'MOCK_ONLY', 'Outbound delivery is kill-switch controlled; no live ERP traffic was authorized.'),
            $this->dependency('Government', 'MOCK_ONLY', 'Filing artifacts are local/demo only; no government submission was authorized.'),
            $this->dependency('Bank / payment', 'MOCK_ONLY', 'Payment batches create instructions only; no bank transfer was authorized.'),
        ];

        $passed = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'PASS'));
        $warnings = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'WARNING'));
        $blockedChecks = count($checks) - $passed - $warnings;
        $blockers = [];
        foreach ($dependencies as $dependency) {
            if ($dependency['status'] === 'BLOCKED') {
                $blockers[] = ['type' => 'EXTERNAL', 'dependency' => $dependency['name'], 'detail' => $dependency['detail']];
            }
        }

        return [
            'classification' => $blockers === [] && $blockedChecks === 0 ? 'STAGING_READY' : 'PRODUCTION_BLOCKED_EXTERNAL',
            'summary' => ['total' => count($checks), 'passed' => $passed, 'warnings' => $warnings, 'blocked' => $blockedChecks + count($blockers)],
            'checks' => $checks,
            'dependencies' => $dependencies,
            'blockers' => $blockers,
            'migration_count' => $this->migrationCount(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array{key:string,label:string,category:string,status:string,detail:string} */
    private function check(string $key, string $label, string $category, bool $passes, string $detail): array
    {
        return compact('key', 'label', 'category', 'detail') + ['status' => $passes ? 'PASS' : 'BLOCKED'];
    }

    /** @return array{name:string,status:string,detail:string} */
    private function dependency(string $name, string $status, string $detail): array
    {
        return compact('name', 'status', 'detail');
    }

    private function reservedDatabase(string $database): bool
    {
        return $database === '' || in_array($database, ['schema_admin', 'schema_admin_testing', 'schema_admin_browser_testing', 'schema_admin_development'], true) || str_starts_with($database, 'schema_admin_testing_');
    }

    private function migrationCount(): int
    {
        $files = glob(database_path('migrations/*.php'));

        return is_array($files) ? count($files) : 0;
    }

    private function migrationInventoryValid(): bool
    {
        $files = glob(database_path('migrations/*.php'));
        if (! is_array($files) || $files === []) {
            return false;
        }

        $names = array_map('basename', $files);

        return count($names) === count(array_unique($names))
            && count(array_filter($files, 'is_readable')) === count($files);
    }

    private function cloudflareConfigured(): bool
    {
        return $this->string(config('services.cloudflare_images.account_id')) !== '' && $this->string(config('services.cloudflare_images.api_token')) !== '';
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
