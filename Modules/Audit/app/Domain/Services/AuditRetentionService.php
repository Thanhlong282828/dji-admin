<?php

namespace Modules\Audit\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Modules\Audit\Application\Commands\ApplyRetentionPolicyCommand;
use Modules\Audit\Application\Commands\ArchiveAuditRangeCommand;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Audit\Domain\Support\AuditChecksum;
use RuntimeException;

final readonly class AuditRetentionService
{
    public function __construct(private ConnectionInterface $db, private AuditRecorderInterface $recorder, private FilesystemManager $storage) {}

    public function apply(ApplyRetentionPolicyCommand $command): int
    {
        return $this->db->transaction(function () use ($command): int {
            $this->db->table('companies')->where('id', $command->companyId)->lockForUpdate()->first();
            $existing = $this->db->table('audit_retention_policies')->where(['company_id' => $command->companyId, 'name' => $command->name])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $checksum = AuditChecksum::make(['company_id' => $command->companyId, 'name' => $command->name, 'retention_days' => $command->retentionDays, 'version' => 1]);
            $id = (int) $this->db->table('audit_retention_policies')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $command->companyId, 'name' => $command->name, 'status' => 'active', 'retention_days' => $command->retentionDays, 'archive_disk' => 'local', 'version' => 1, 'checksum' => $checksum, 'created_by' => $command->actorId, 'created_at' => now(), 'updated_at' => now()]);
            $this->recorder->record(new AuditEventData($command->companyId, 'user', $command->actorId, 'retention_policy_applied', 'Audit', 'RetentionPolicyApplied', 'audit_retention_policy', (string) $id, [], ['status' => 'active', 'retention_days' => $command->retentionDays], [], 'retention:'.$command->companyId.':'.$command->name));

            return $id;
        }, 5);
    }

    public function archive(ArchiveAuditRangeCommand $command): int
    {
        return $this->db->transaction(function () use ($command): int {
            $this->db->table('companies')->where('id', $command->companyId)->lockForUpdate()->first();
            $existing = $this->db->table('audit_archive_artifacts')->where(['company_id' => $command->companyId, 'idempotency_key' => $command->idempotencyKey])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $start = CarbonImmutable::parse($command->rangeStart)->startOfDay();
            $end = CarbonImmutable::parse($command->rangeEnd)->endOfDay();
            if ($end->lessThan($start)) {
                throw new \DomainException('Archive range end must not precede range start.');
            }
            $uuid = (string) Str::uuid();
            $reference = $uuid.'.jsonl';
            $path = "audit-archive/{$command->companyId}/{$reference}";
            $stream = fopen('php://temp/maxmemory:5242880', 'w+b');
            if ($stream === false) {
                throw new RuntimeException('Unable to create archive stream.');
            }
            fwrite($stream, json_encode(['archive_format' => 'schema-enterprise-audit-jsonl-v1', 'company_id' => $command->companyId, 'range_start' => $start->toIso8601String(), 'range_end' => $end->toIso8601String()], JSON_THROW_ON_ERROR)."\n");
            $count = 0;
            $rows = $this->db->table('audit_events')->where('company_id', $command->companyId)->whereBetween('occurred_at', [$start, $end])->orderBy('id')->cursor();
            foreach ($rows as $row) {
                $values = get_object_vars($row);
                $safe = ['uuid' => $this->text($values['uuid'] ?? null), 'source_module' => $this->text($values['source_module'] ?? null), 'source_event' => $this->text($values['source_event'] ?? null), 'resource_type' => $this->text($values['resource_type'] ?? null), 'resource_id' => $this->text($values['resource_id'] ?? null), 'action' => $this->text($values['action'] ?? null), 'occurred_at' => $this->text($values['occurred_at'] ?? null), 'payload_checksum' => $this->text($values['payload_checksum'] ?? null)];
                fwrite($stream, json_encode($safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
                $count++;
            }
            $stats = fstat($stream);
            $size = $stats === false ? 0 : $stats['size'];
            $checksum = $this->streamChecksum($stream);
            rewind($stream);
            $written = $this->storage->disk('local')->writeStream($path, $stream);
            fclose($stream);
            if (! $written) {
                throw new RuntimeException('Unable to persist immutable audit archive.');
            }
            $id = (int) $this->db->table('audit_archive_artifacts')->insertGetId(['uuid' => $uuid, 'company_id' => $command->companyId, 'range_start' => $start, 'range_end' => $end, 'event_count' => $count, 'checksum' => $checksum, 'artifact_reference' => $reference, 'storage_disk' => 'local', 'mime_type' => 'application/x-ndjson', 'file_size' => $size, 'status' => 'archived', 'idempotency_key' => $command->idempotencyKey, 'created_by' => $command->actorId, 'created_at' => now()]);
            $this->recorder->record(new AuditEventData($command->companyId, 'user', $command->actorId, 'archived', 'Audit', 'AuditRangeArchived', 'audit_archive_artifact', (string) $id, [], ['status' => 'archived', 'event_count' => $count, 'checksum' => $checksum], ['range_start' => $start->toIso8601String(), 'range_end' => $end->toIso8601String(), 'physical_delete' => false], 'archive-event:'.$command->companyId.':'.$command->idempotencyKey));

            return $id;
        }, 5);
    }

    public function verifyArchive(int $companyId, int $artifactId): bool
    {
        $artifact = $this->db->table('audit_archive_artifacts')->where(['id' => $artifactId, 'company_id' => $companyId])->first();
        if ($artifact === null) {
            return false;
        }
        $values = get_object_vars($artifact);
        $reference = $this->text($values['artifact_reference'] ?? null);
        if ($reference === '' || str_contains($reference, '/') || str_contains($reference, '\\')) {
            return false;
        }
        $path = "audit-archive/{$companyId}/{$reference}";
        $disk = $this->storage->disk($this->text($values['storage_disk'] ?? null, 'local'));
        if (! $disk->exists($path)) {
            return false;
        }
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return false;
        }
        $stats = fstat($stream);
        $size = $stats === false ? -1 : $stats['size'];
        $checksum = $this->streamChecksum($stream);
        fclose($stream);

        $expectedSize = is_numeric($values['file_size'] ?? null) ? (int) $values['file_size'] : -1;

        return $size === $expectedSize && hash_equals($this->text($values['checksum'] ?? null), $checksum);
    }

    private function text(mixed $value, string $fallback = ''): string
    {
        return is_scalar($value) ? (string) $value : $fallback;
    }

    /** @param resource $stream */
    private function streamChecksum($stream): string
    {
        rewind($stream);
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);

        return hash_final($context);
    }
}
