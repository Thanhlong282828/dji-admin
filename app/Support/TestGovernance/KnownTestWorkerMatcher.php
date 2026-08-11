<?php

namespace App\Support\TestGovernance;

final class KnownTestWorkerMatcher
{
    /** @return list<array{pid:int,command:string}> */
    public function matching(string $processList): array
    {
        $project = base_path();
        $matches = [];
        foreach (preg_split('/\R/', $processList) ?: [] as $line) {
            if (! str_contains($line, $project.'/tests/Support/workforce_concurrency_worker.php')) {
                continue;
            }
            if (preg_match('/^\s*(\d+)\s+(.+)$/', $line, $parts) !== 1) {
                continue;
            }
            $matches[] = ['pid' => (int) $parts[1], 'command' => basename($project).'/tests/Support/workforce_concurrency_worker.php'];
        }

        return $matches;
    }
}
