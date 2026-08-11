<?php

namespace App\Console\Commands;

use App\Support\Secrets\SecretCatalogEntry;
use App\Support\Secrets\SecretGovernanceService;
use Illuminate\Console\Command;

final class AuditSecretsCommand extends Command
{
    protected $signature = 'operations:secrets-audit {--json}';

    protected $description = 'Read-only secret reference, presence and rotation metadata audit';

    public function handle(SecretGovernanceService $service): int
    {
        $readiness = $service->readiness();
        $entries = array_map(static fn (SecretCatalogEntry $entry): array => $entry->toArray(), $service->entries());
        $payload = ['schema_version' => 1, 'environment' => app()->environment(), 'readiness' => $readiness, 'entries' => $entries];
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Reference', 'Component', 'Class', 'Source', 'Status', 'Rotation'], array_map(static fn (array $entry): array => [$entry['reference'], $entry['component'], $entry['classification'], $entry['source'], $entry['status'], $entry['rotation_due']], $entries));
            $this->info($readiness['ready'] ? 'Required secret readiness: PASS' : 'Required secret readiness: FAIL');
        }

        return $readiness['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
