<?php

namespace App\Console\Commands;

use App\Support\Release\MigrationRiskScanner;
use Illuminate\Console\Command;

final class ScanMigrationRiskCommand extends Command
{
    protected $signature = 'operations:migration-risk {--path=database/migrations} {--fail-on-blocking}';

    protected $description = 'Classify migration files for release manual review without executing them';

    public function handle(MigrationRiskScanner $scanner): int
    {
        $path = $this->option('path');
        $directory = base_path(is_string($path) ? $path : 'database/migrations');
        $reports = $scanner->scan($directory);
        $this->line(json_encode(['status' => 'complete', 'migrations' => $reports], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $blocking = array_filter($reports, static fn (array $report): bool => $report['risk'] === 'blocking');

        return $this->option('fail-on-blocking') && $blocking !== [] ? self::FAILURE : self::SUCCESS;
    }
}
