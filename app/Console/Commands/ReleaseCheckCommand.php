<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Stringable;
use Throwable;

final class ReleaseCheckCommand extends Command
{
    protected $signature = 'operations:release-check
        {--image= : Immutable image tag@digest}
        {--rollback-target= : Previous immutable image tag@digest}
        {--quality-evidence= : Existing quality evidence JSON}
        {--backup-reference= : Backup readiness/change reference}
        {--allow-reviewed-pending : Allow pending migrations only after scanner/manual review}
        {--allow-stale-scheduler : Non-production testing override}';

    protected $description = 'Run read-only pre-deployment release gates and report PASS or FAIL';

    public function handle(): int
    {
        $checks = [
            'environment_contract' => $this->environmentContract(),
            'debug_disabled_in_production' => ! app()->environment('production') || ! (bool) config('app.debug'),
            'database_connectivity' => $this->databaseReady(),
            'migrations_current_or_reviewed' => $this->migrationsCurrent() || (bool) $this->option('allow-reviewed-pending'),
            'redis_readiness' => $this->redisReady(),
            'scheduler_heartbeat' => $this->schedulerReady(),
            'storage_writable' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
            'config_cache_compatible' => is_dir(base_path('bootstrap/cache')),
            'immutable_image' => $this->immutableImage((string) $this->option('image')),
            'rollback_target' => $this->immutableImage((string) $this->option('rollback-target')),
            'quality_evidence' => $this->safeFile((string) $this->option('quality-evidence')),
            'backup_readiness' => trim((string) $this->option('backup-reference')) !== '',
        ];
        $passed = ! in_array(false, $checks, true);
        $this->line(json_encode(['status' => $passed ? 'pass' : 'fail', 'environment' => app()->environment(), 'database' => DB::connection()->getDatabaseName(), 'checks' => $checks], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function environmentContract(): bool
    {
        $database = DB::connection()->getDatabaseName();

        return match (app()->environment()) {
            'production' => $database === 'schema_admin',
            'local' => str_ends_with($database, '_development'),
            'testing' => str_contains($database, '_testing'),
            default => false,
        };
    }

    private function databaseReady(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function migrationsCurrent(): bool
    {
        try {
            $ranValues = DB::table('migrations')->pluck('migration')->all();
            $ran = [];
            foreach ($ranValues as $value) {
                if (is_string($value)) {
                    $ran[] = $value;
                }
            }
            $files = glob(database_path('migrations/*.php'));
            if (! is_array($files)) {
                return false;
            }
            $expected = array_map(static fn (string $file): string => basename($file, '.php'), $files);

            return array_diff($expected, $ran) === [];
        } catch (Throwable) {
            return false;
        }
    }

    private function redisReady(): bool
    {
        try {
            $result = Redis::connection()->ping();

            return $result === true || $result === 'PONG' || ($result instanceof Stringable && (string) $result === 'PONG');
        } catch (Throwable) {
            return false;
        }
    }

    private function schedulerReady(): bool
    {
        if ($this->option('allow-stale-scheduler') && ! app()->environment('production')) {
            return true;
        }

        try {
            return is_string(Cache::get('operations:scheduler:heartbeat'));
        } catch (Throwable) {
            return false;
        }
    }

    private function immutableImage(string $image): bool
    {
        return preg_match('/^[a-zA-Z0-9._\/-]+:[0-9a-f]{40}@sha256:[0-9a-f]{64}$/', $image) === 1;
    }

    private function safeFile(string $path): bool
    {
        if (preg_match('#^ci://[A-Za-z0-9._:/-]{8,240}$#', $path) === 1) {
            return true;
        }

        return $path !== '' && is_file($path) && is_readable($path) && filesize($path) !== false && filesize($path) > 0;
    }
}
