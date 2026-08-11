<?php

namespace App\Console\Commands;

use App\Support\DataProtection\DataProtectionAuditService;
use Illuminate\Console\Command;

final class DataProtectionAuditCommand extends Command
{
    protected $signature = 'operations:data-protection-audit {--json : Emit deterministic JSON}';

    protected $description = 'Audit data-protection policy metadata without reading protected values';

    public function handle(DataProtectionAuditService $service): int
    {
        $audit = $service->audit();
        if ($this->option('json')) {
            $this->line(json_encode($audit, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Enterprise data-protection audit: '.strtoupper($audit['status']));
            $this->table(['Classification', 'Registered fields'], collect($audit['classification_counts'])->map(fn (int $count, string $classification): array => [$classification, $count])->values()->all());
            $this->line('Policy checksum: '.$audit['checksum']);
            $this->line('Cryptographic mechanisms: '.$audit['crypto_count']);
        }

        return self::SUCCESS;
    }
}
