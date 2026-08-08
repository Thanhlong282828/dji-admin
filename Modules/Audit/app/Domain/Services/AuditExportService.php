<?php

namespace Modules\Audit\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Modules\Audit\Application\Commands\CreateAuditExportCommand;
use Modules\Audit\Domain\Support\AuditChecksum;
use Modules\Audit\Infrastructure\Export\StreamingPdfWriter;
use RuntimeException;

final readonly class AuditExportService
{
    public function __construct(private ConnectionInterface $db, private FilesystemManager $storage, private StreamingPdfWriter $pdfWriter) {}

    public function create(CreateAuditExportCommand $command): int
    {
        return $this->db->transaction(function () use ($command): int {
            $this->db->table('companies')->where('id', $command->companyId)->lockForUpdate()->first();
            $existing = $this->db->table('audit_exports')->where(['company_id' => $command->companyId, 'idempotency_key' => $command->idempotencyKey])->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }

            $rows = $this->db->table('audit_events')->where('company_id', $command->companyId)->orderBy('id')->cursor();
            $uuid = (string) Str::uuid();
            $extension = $command->format === 'pdf' ? 'pdf' : 'csv';
            $path = "audit/{$command->companyId}/{$uuid}.{$extension}";
            $temporary = fopen('php://temp/maxmemory:5242880', 'w+b');
            if ($temporary === false) {
                throw new RuntimeException('Unable to create audit export stream.');
            }

            $count = 0;
            if ($extension === 'csv') {
                fwrite($temporary, "\xEF\xBB\xBF");
                fputcsv($temporary, ['UUID', 'Occurred At', 'Actor', 'Action', 'Module', 'Resource Type', 'Resource ID', 'Correlation', 'Checksum']);
                foreach ($rows as $row) {
                    $values = get_object_vars($row);
                    fputcsv($temporary, [$this->text($values['uuid'] ?? null), $this->text($values['occurred_at'] ?? null), $this->text($values['actor_id'] ?? $values['actor_type'] ?? null), $this->text($values['action'] ?? null), $this->text($values['source_module'] ?? null), $this->text($values['resource_type'] ?? null), $this->safeCell($this->text($values['resource_id'] ?? null)), $this->text($values['correlation_id'] ?? null), $this->text($values['payload_checksum'] ?? null)]);
                    $count++;
                }
            } else {
                $count = $this->pdfWriter->write($temporary, $this->pdfRows($rows));
            }
            $disk = $this->storage->disk('local');
            $stats = fstat($temporary);
            $size = $stats === false ? 0 : $stats['size'];
            $checksum = $this->streamChecksum($temporary);
            rewind($temporary);
            $written = $disk->writeStream($path, $temporary);
            fclose($temporary);
            if (! $written || $size <= 0) {
                throw new RuntimeException('Unable to persist audit export stream.');
            }
            $mime = $extension === 'pdf' ? 'application/pdf' : 'text/csv';
            $id = (int) $this->db->table('audit_exports')->insertGetId(['uuid' => $uuid, 'company_id' => $command->companyId, 'status' => 'completed', 'format' => $extension, 'filters' => json_encode($command->filters, JSON_THROW_ON_ERROR), 'storage_disk' => 'local', 'storage_path' => $path, 'file_name' => "enterprise-audit-{$uuid}.{$extension}", 'mime_type' => $mime, 'file_size' => $size, 'checksum' => $checksum, 'idempotency_key' => $command->idempotencyKey, 'generated_by' => $command->actorId, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $payload = ['export_id' => $id, 'format' => $extension, 'row_count' => $count, 'checksum' => $checksum];
            $this->db->table('audit_outbox')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $command->companyId, 'event_type' => 'audit.export.created', 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => AuditChecksum::make($payload), 'idempotency_key' => 'audit-export:'.$command->companyId.':'.$command->idempotencyKey, 'available_at' => now(), 'created_at' => now()]);

            return $id;
        }, 5);
    }

    /** @return array{contents: string, name: string, mime: string} */
    public function verifiedArtifact(int $companyId, int $exportId, int $actorId): array
    {
        $export = $this->db->table('audit_exports')->where(['id' => $exportId, 'company_id' => $companyId])->first();
        abort_if($export === null, 404);
        $values = get_object_vars($export);
        $diskName = is_string($values['storage_disk'] ?? null) ? $values['storage_disk'] : 'local';
        $path = is_string($values['storage_path'] ?? null) ? $values['storage_path'] : '';
        $disk = $this->storage->disk($diskName);
        $valid = $path !== '' && $disk->exists($path);
        $loaded = $valid ? $disk->get($path) : null;
        $contents = is_string($loaded) ? $loaded : '';
        $fileSize = is_numeric($values['file_size'] ?? null) ? (int) $values['file_size'] : -1;
        $storedChecksum = is_string($values['checksum'] ?? null) ? $values['checksum'] : '';
        $format = is_string($values['format'] ?? null) ? $values['format'] : '';
        $valid = $valid && strlen($contents) === $fileSize && hash_equals($storedChecksum, hash('sha256', $contents));
        $valid = $valid && (($format === 'pdf' && str_starts_with($contents, '%PDF-')) || ($format === 'csv' && str_starts_with($contents, "\xEF\xBB\xBF")));
        $this->db->table('audit_access_logs')->insert(['company_id' => $companyId, 'actor_id' => $actorId, 'action' => 'audit_export_download', 'result' => $valid ? 'success' : 'blocked', 'checksum' => AuditChecksum::make(['export_id' => $exportId, 'result' => $valid ? 'success' : 'blocked']), 'accessed_at' => now()]);
        abort_unless($valid, 409, 'Audit export integrity verification failed.');

        return ['contents' => $contents, 'name' => $this->text($values['file_name'] ?? null), 'mime' => $this->text($values['mime_type'] ?? null)];
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return \Generator<int, array{uuid:string,occurred_at:string,action:string,resource:string,checksum:string}>
     */
    private function pdfRows(iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            $values = get_object_vars($row);
            yield ['uuid' => $this->text($values['uuid'] ?? null), 'occurred_at' => $this->text($values['occurred_at'] ?? null), 'action' => $this->text($values['action'] ?? null), 'resource' => $this->text($values['resource_type'] ?? null).':'.$this->text($values['resource_id'] ?? null), 'checksum' => $this->text($values['payload_checksum'] ?? null)];
        }
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
