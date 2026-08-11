<?php

namespace App\Console\Commands;

use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use Illuminate\Console\Command;

final class AuditRuntimeConfigurationCommand extends Command
{
    protected $signature = 'operations:config-audit {--json}';

    protected $description = 'Read-only audit of non-secret typed runtime configuration';

    public function handle(RuntimeConfigurationService $service): int
    {
        $entries = array_map(static fn ($entry): array => $entry->toArray(), $service->entries());
        $schemaVersion = config('runtime-configuration.schema_version', 1);
        $payload = ['schema_version' => is_int($schemaVersion) ? $schemaVersion : 1, 'environment' => app()->environment(), ...$service->metrics(), 'snapshot_checksum' => $service->snapshotChecksum(), 'entries' => $entries];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Key', 'Enabled', 'Source', 'Risk', 'Failure', 'Drift'], array_map(static fn (array $entry): array => [$entry['key'], $entry['enabled'] ? 'yes' : 'no', $entry['source'], $entry['risk'], $entry['failure_mode'], $entry['drift_status']], $entries));
            $this->info('Logical drift: '.$payload['drift_count'].'; checksum: '.$payload['snapshot_checksum']);
        }

        return self::SUCCESS;
    }
}
