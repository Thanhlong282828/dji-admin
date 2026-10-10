<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

final class MysqlProcessRaceRunner
{
    private const READY_TIMEOUT_SECONDS = 45;

    /**
     * @param  array<string,bool|int|string|null>  $payloadA
     * @param  array<string,bool|int|string|null>  $payloadB
     * @return array{exit_codes:list<int>,outputs:list<string>}
     */
    public function run(string $scenario, array $payloadA, array $payloadB, ?string $scenarioB = null): array
    {
        $this->assertSafeRuntime();
        $token = bin2hex(random_bytes(16));
        $directory = sys_get_temp_dir().'/schema-workforce-'.$token;
        if (file_exists($directory) || ! mkdir($directory, 0700)) {
            throw new RuntimeException('Unable to create a clean race directory.');
        }
        $barrier = $directory.'/release.marker';
        $paths = [$directory.'/a.json', $directory.'/b.json'];
        file_put_contents($paths[0], json_encode($payloadA, JSON_THROW_ON_ERROR));
        file_put_contents($paths[1], json_encode($payloadB, JSON_THROW_ON_ERROR));
        $worker = base_path('tests/Support/workforce_concurrency_worker.php');
        $workerEnvironment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'schema_admin_testing', 'MEDIA_DRIVER' => 'local'];
        $processes = [new Process([PHP_BINARY, $worker, $scenario, $barrier, $paths[0]], base_path(), $workerEnvironment), new Process([PHP_BINARY, $worker, $scenarioB ?? $scenario, $barrier, $paths[1]], base_path(), $workerEnvironment)];
        $startedAt = $this->monotonicSeconds();

        try {
            foreach ($processes as $process) {
                $process->setTimeout(120);
                $process->setIdleTimeout(100);
                $process->start();
            }
            $readyDeadline = $this->monotonicSeconds() + self::READY_TIMEOUT_SECONDS;
            while (count(glob($directory.'/*.ready') ?: []) < 2 && $this->monotonicSeconds() < $readyDeadline) {
                $this->assertProcessesDidNotExitEarly($processes, $startedAt);
                usleep(20_000);
            }
            if (count(glob($directory.'/*.ready') ?: []) !== 2) {
                throw new RuntimeException($this->diagnostic('Workers did not reach ready barrier.', $processes, $directory, $startedAt));
            }
            $temporaryRelease = $directory.'/release.tmp';
            file_put_contents($temporaryRelease, 'go', LOCK_EX);
            if (! rename($temporaryRelease, $barrier)) {
                throw new RuntimeException('Unable to publish release marker atomically.');
            }
            $completionDeadline = $this->monotonicSeconds() + 60;
            while ($this->hasRunningProcess($processes) && $this->monotonicSeconds() < $completionDeadline) {
                foreach ($processes as $process) {
                    $process->checkTimeout();
                }
                usleep(20_000);
            }
            if ($this->hasRunningProcess($processes)) {
                throw new RuntimeException($this->diagnostic('Workers exceeded completion deadline.', $processes, $directory, $startedAt));
            }

            $exitCodes = array_map(static fn (Process $process): int => $process->getExitCode() ?? -1, $processes);
            sort($exitCodes);

            return ['exit_codes' => $exitCodes, 'outputs' => array_map(static fn (Process $process): string => $process->getOutput().$process->getErrorOutput(), $processes)];
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1, SIGTERM);
                }
            }
            foreach (glob($directory.'/*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($directory);
        }
    }

    /** @param list<Process> $processes */
    private function hasRunningProcess(array $processes): bool
    {
        return array_any($processes, static fn (Process $process): bool => $process->isRunning());
    }

    /** @param list<Process> $processes */
    private function assertProcessesDidNotExitEarly(array $processes, float $startedAt): void
    {
        foreach ($processes as $process) {
            if (! $process->isRunning()) {
                throw new RuntimeException($this->diagnostic('Worker exited before release.', $processes, 'not-released', $startedAt));
            }
        }
    }

    /** @param list<Process> $processes */
    private function diagnostic(string $message, array $processes, string $barrier, float $startedAt): string
    {
        $readyCount = is_dir($barrier) ? count(glob($barrier.'/*.ready') ?: []) : 0;
        $details = [$message, 'barrier='.$barrier, sprintf('elapsed_ms=%d ready=%d env=testing connection=mysql database=schema_admin_testing', (int) (($this->monotonicSeconds() - $startedAt) * 1000), $readyCount)];
        foreach ($processes as $index => $process) {
            $details[] = sprintf('worker[%d] pid=%s running=%s exit=%s stdout=%s stderr=%s', $index, (string) $process->getPid(), $process->isRunning() ? 'yes' : 'no', (string) $process->getExitCode(), trim($process->getOutput()), trim($process->getErrorOutput()));
        }

        return implode("\n", $details);
    }

    private function assertSafeRuntime(): void
    {
        $connection = DB::connection();
        if (! app()->environment('testing') || $connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'schema_admin_testing') {
            throw new RuntimeException('Concurrency runner requires testing/mysql/schema_admin_testing.');
        }
    }

    private function monotonicSeconds(): float
    {
        return hrtime(true) / 1_000_000_000;
    }
}
