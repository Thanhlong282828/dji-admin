<?php

namespace Tests\Unit\Operations;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class RuntimeResourceHardeningTest extends TestCase
{
    public function test_default_queue_worker_has_bounded_lifetime_and_safe_retry_window(): void
    {
        $compose = $this->projectFile('compose.yml');

        self::assertStringContainsString('--timeout=330', $compose);
        self::assertStringContainsString('--memory=384', $compose);
        self::assertStringContainsString('--max-jobs=250', $compose);
        self::assertStringContainsString('--max-time=3600', $compose);
        $retryAfter = config('queue.connections.redis.retry_after');
        self::assertIsInt($retryAfter);
        self::assertGreaterThan(330, $retryAfter);
    }

    public function test_opt_in_horizon_workers_are_bounded(): void
    {
        self::assertSame(1, config('horizon.environments.production.supervisor-1.maxProcesses'));
        self::assertSame(3600, config('horizon.defaults.supervisor-1.maxTime'));
        self::assertSame(250, config('horizon.defaults.supervisor-1.maxJobs'));
        self::assertSame(384, config('horizon.defaults.supervisor-1.memory'));
        self::assertSame(330, config('horizon.defaults.supervisor-1.timeout'));
    }

    public function test_php_fpm_and_container_logs_recycle_with_explicit_bounds(): void
    {
        $pool = $this->projectFile('docker/php/zz-runtime-pool.conf');
        $compose = $this->projectFile('compose.yml');

        self::assertStringContainsString('pm = ondemand', $pool);
        self::assertStringContainsString('pm.max_children = 2', $pool);
        self::assertStringContainsString('pm.max_requests = 300', $pool);
        self::assertStringContainsString('max-size: "10m"', $compose);
        self::assertStringContainsString('max-file: "3"', $compose);
        self::assertSame(['stderr'], config('logging.channels.production.channels'));
    }

    public function test_operational_pruning_is_canonical_bounded_and_non_business(): void
    {
        $commands = collect($this->app->make(Schedule::class)->events())
            ->map(static fn ($event): string => (string) $event->command)
            ->filter(static fn (string $command): bool => str_contains($command, 'queue:prune-'))
            ->values();

        self::assertCount(2, $commands);
        self::assertSame(1, $commands->filter(static fn (string $command): bool => str_contains($command, 'queue:prune-failed --hours=336'))->count());
        self::assertSame(1, $commands->filter(static fn (string $command): bool => str_contains($command, 'queue:prune-batches --hours=168 --unfinished=168 --cancelled=168'))->count());

        $consoleRoutes = $this->projectFile('routes/console.php');
        foreach (['storage/app/private', 'storage/app/public', 'storage/app/recovery', 'activity_log', 'accounting', 'inventory'] as $protected) {
            self::assertStringNotContainsString('delete('.$protected, $consoleRoutes);
        }
    }

    public function test_systemd_templates_use_measured_graceful_bounds_without_hard_memory_kills(): void
    {
        $queue = $this->projectFile('deploy/systemd/laravel-queue.service');
        $pool = $this->projectFile('deploy/php-fpm/zz-runtime-pool.conf');

        foreach (['--timeout=330', '--memory=192', '--max-jobs=250', '--max-time=3600'] as $bound) {
            self::assertStringContainsString($bound, $queue);
        }
        self::assertStringContainsString('TimeoutStopSec=390', $queue);
        self::assertStringContainsString('KillSignal=SIGTERM', $queue);
        self::assertStringNotContainsString('MemoryMax=', $queue);
        self::assertStringContainsString('pm = ondemand', $pool);
        self::assertStringContainsString('pm.max_children = 2', $pool);
        self::assertStringContainsString('pm.max_requests = 300', $pool);
    }

    public function test_systemd_scheduler_and_reverb_templates_preserve_current_topology(): void
    {
        $scheduler = $this->projectFile('deploy/systemd/laravel-scheduler.service');
        $timer = $this->projectFile('deploy/systemd/laravel-scheduler.timer');
        $reverb = $this->projectFile('deploy/systemd/laravel-reverb.service');

        self::assertStringContainsString('Type=oneshot', $scheduler);
        self::assertStringContainsString('artisan schedule:run', $scheduler);
        self::assertStringContainsString('Persistent=true', $timer);
        self::assertStringContainsString('artisan reverb:start --host=127.0.0.1 --port=8080', $reverb);
        self::assertStringNotContainsString('MemoryMax=', $reverb);
    }

    public function test_document_storage_runtime_is_packaged_and_preflighted_for_vps(): void
    {
        $dockerfile = $this->projectFile('Dockerfile');
        $preflight = $this->projectFile('scripts/operations/document-storage-vps-preflight.sh');
        $provider = $this->projectFile('Modules/Document/app/Providers/DocumentServiceProvider.php');

        self::assertStringContainsString('clamav-scanner freshclam', $dockerfile);
        self::assertStringContainsString('CLAMAV_FRESHCLAM_TIMEOUT_SECONDS=300', $dockerfile);
        self::assertStringContainsString('laraveldji-clamav-update', $dockerfile);
        $signatureUpdater = $this->projectFile('docker/clamav/update-signatures.sh');
        self::assertStringContainsString('timeout "$timeout_seconds" freshclam', $signatureUpdater);
        self::assertStringContainsString("sed '/^[[:space:]]*NotifyClamd", $signatureUpdater);
        self::assertStringContainsString('timeout 30 clamscan', $signatureUpdater);
        self::assertStringContainsString('freshclam completed without a non-empty official signature database', $signatureUpdater);
        self::assertStringNotContainsString('touch ', $signatureUpdater);
        self::assertStringNotContainsString('clamd', $dockerfile);
        self::assertStringContainsString('documents:storage-readiness --strict-production', $preflight);
        self::assertStringContainsString('APP_ENV must be production', $preflight);
        self::assertStringContainsString('DOCUMENT_RECONCILIATION_SCHEDULE_ENABLED', $this->projectFile('.env.example'));
        self::assertStringContainsString('documents:storage-reconcile-tenants', $provider);
        self::assertStringContainsString('everyFiveMinutes()', $provider);
        self::assertStringContainsString('onOneServer()', $provider);
        self::assertStringContainsString('withoutOverlapping(10)', $provider);
    }

    private function projectFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));
        self::assertIsString($contents);

        return $contents;
    }
}
