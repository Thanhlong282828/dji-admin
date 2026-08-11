<?php

namespace App\Console\Commands;

use App\Support\Security\SecurityAssuranceService;
use Illuminate\Console\Command;

final class SecurityAssuranceCommand extends Command
{
    protected $signature = 'operations:security-assurance {--json}';

    protected $description = 'Report the read-only enterprise security assurance posture';

    public function handle(SecurityAssuranceService $service): int
    {
        $snapshot = $service->snapshot();
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        $this->info('Security assurance: '.strtoupper($snapshot['summary']['status']));
        $this->line(sprintf('Routes: %d; privileged: %d; open critical/high: %d/%d', $snapshot['summary']['routes'], $snapshot['summary']['privileged'], $snapshot['summary']['critical'], $snapshot['summary']['high']));

        return self::SUCCESS;
    }
}
