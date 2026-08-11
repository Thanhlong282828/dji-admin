<?php

namespace App\Console\Commands;

use App\Support\TestGovernance\KnownTestWorkerMatcher;
use App\Support\TestGovernance\SafeTestDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

final class TestReadinessCommand extends Command
{
    protected $signature = 'operations:test-readiness {--json : Emit safe JSON}';

    protected $description = 'Read-only test environment, dependency, migration, browser and process readiness check';

    public function handle(SafeTestDatabase $databaseGuard, KnownTestWorkerMatcher $matcher): int
    {
        $environment = app()->environment();
        $defaultConnection = config('database.default');
        $defaultConnection = is_string($defaultConnection) ? $defaultConnection : '';
        $configuredDatabase = config('database.connections.'.$defaultConnection.'.database', '');
        $database = is_string($configuredDatabase) ? $configuredDatabase : '';
        $mysql = $this->probe(static fn (): mixed => DB::selectOne('SELECT 1 AS ready'));
        $process = new Process(['ps', '-axo', 'pid=,command=']);
        $process->run();
        $orphans = $matcher->matching($process->isSuccessful() ? $process->getOutput() : '');
        $chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
        $snapshot = [
            'environment' => $environment,
            'database' => ['name' => $database, 'safe' => $databaseGuard->allows($environment, $database), 'reachable' => $mysql],
            'redis' => ['reachable' => $this->probe(static fn (): mixed => app('redis')->connection()->ping())],
            'migrations' => ['pending' => $this->pendingMigrations()],
            'workers' => ['known_orphans' => count($orphans)],
            'browser' => ['playwright_core' => is_dir(base_path('node_modules/playwright-core')), 'chrome' => is_executable($chrome)],
            'docker' => ['available' => $this->commandAvailable('docker')],
        ];
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->info('Test readiness: read-only');
            $this->table(['Gate', 'Result'], [
                ['Environment/database safety', $snapshot['database']['safe'] ? 'PASS' : 'FAIL'],
                ['MySQL', $snapshot['database']['reachable'] ? 'READY' : 'UNAVAILABLE'],
                ['Redis', $snapshot['redis']['reachable'] ? 'READY' : 'UNAVAILABLE'],
                ['Pending migrations', $snapshot['migrations']['pending']],
                ['Known orphan workers', $snapshot['workers']['known_orphans']],
                ['Playwright/Chrome', $snapshot['browser']['playwright_core'] && $snapshot['browser']['chrome'] ? 'READY' : 'UNAVAILABLE'],
                ['Docker CLI', $snapshot['docker']['available'] ? 'READY' : 'UNAVAILABLE'],
            ]);
        }

        return $snapshot['database']['safe'] && $snapshot['database']['reachable'] && $snapshot['workers']['known_orphans'] === 0 && $snapshot['migrations']['pending'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param callable(): mixed $callback */
    private function probe(callable $callback): bool
    {
        try {
            $callback();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pendingMigrations(): int
    {
        try {
            $files = array_keys(app('migrator')->getMigrationFiles(database_path('migrations')));
            /** @var list<string> $ran */
            $ran = DB::table('migrations')->pluck('migration')->map(static fn (mixed $migration): string => is_string($migration) ? $migration : '')->all();

            return count(array_diff($files, $ran));
        } catch (Throwable) {
            return -1;
        }
    }

    private function commandAvailable(string $command): bool
    {
        $process = new Process(['sh', '-lc', 'command -v '.escapeshellarg($command)]);
        $process->run();

        return $process->isSuccessful();
    }
}
