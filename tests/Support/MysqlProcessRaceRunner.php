<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

final class MysqlProcessRaceRunner
{
    /**
     * @param  array<string,bool|int|string|null>  $payloadA
     * @param  array<string,bool|int|string|null>  $payloadB
     * @return array{exit_codes:list<int>,outputs:list<string>}
     */
    public function run(string $scenario, array $payloadA, array $payloadB, ?string $scenarioB = null): array
    {
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
        $processes = [new Process([PHP_BINARY, $worker, $scenario, $barrier, $paths[0]], base_path()), new Process([PHP_BINARY, $worker, $scenarioB ?? $scenario, $barrier, $paths[1]], base_path())];

        try {
            foreach ($processes as $process) {
                $process->setTimeout(60);
                $process->setIdleTimeout(50);
                $process->start();
            }
            $readyDeadline = microtime(true) + 20;
            while (count(glob($directory.'/*.ready') ?: []) < 2 && microtime(true) < $readyDeadline) {
                $this->assertProcessesDidNotExitEarly($processes);
                usleep(20_000);
            }
            if (count(glob($directory.'/*.ready') ?: []) !== 2) {
                throw new RuntimeException($this->diagnostic('Workers did not reach ready barrier.', $processes, $directory));
            }
            $temporaryRelease = $directory.'/release.tmp';
            file_put_contents($temporaryRelease, 'go', LOCK_EX);
            if (! rename($temporaryRelease, $barrier)) {
                throw new RuntimeException('Unable to publish release marker atomically.');
            }
            $completionDeadline = microtime(true) + 60;
            while ($this->hasRunningProcess($processes) && microtime(true) < $completionDeadline) {
                foreach ($processes as $process) {
                    $process->checkTimeout();
                }
                usleep(20_000);
            }
            if ($this->hasRunningProcess($processes)) {
                throw new RuntimeException($this->diagnostic('Workers exceeded completion deadline.', $processes, $directory));
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
    private function assertProcessesDidNotExitEarly(array $processes): void
    {
        foreach ($processes as $process) {
            if (! $process->isRunning()) {
                throw new RuntimeException($this->diagnostic('Worker exited before release.', $processes, 'not-released'));
            }
        }
    }

    /** @param list<Process> $processes */
    private function diagnostic(string $message, array $processes, string $barrier): string
    {
        $details = [$message, 'barrier='.$barrier];
        foreach ($processes as $index => $process) {
            $details[] = sprintf('worker[%d] pid=%s exit=%s stdout=%s stderr=%s', $index, (string) $process->getPid(), (string) $process->getExitCode(), trim($process->getOutput()), trim($process->getErrorOutput()));
        }

        return implode("\n", $details);
    }
}
