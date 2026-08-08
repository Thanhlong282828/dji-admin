<?php

namespace Modules\Integration\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Integration\Domain\Support\IntegrationChecksum;

final readonly class EnterpriseDomainIntegrationBridge
{
    public function __construct(private ConnectionInterface $db, private IntegrationHubService $hub) {}

    public function queueErpExport(int $companyId, int $erpExportId, int $connectionId, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $erpExportId, $connectionId, $actorId): int {
            $export = $this->db->table('erp_exports')->where(['company_id' => $companyId, 'id' => $erpExportId])->lockForUpdate()->first();
            abort_unless(is_object($export), 404);
            if ($this->string($export, 'status') !== 'exported') {
                throw ValidationException::withMessages(['erp_export' => 'ERP export must be immutable and exported before transport.']);
            }
            $this->verifyArtifact($export, 'artifact_disk', 'artifact_path', 'artifact_size', 'artifact_checksum');
            $version = $this->integer($export, 'export_version');
            $payload = ['source' => 'erp_export', 'source_id' => $erpExportId, 'version' => $version, 'artifact_uuid' => $this->string($export, 'uuid'), 'artifact_name' => $this->string($export, 'artifact_name'), 'mime' => $this->string($export, 'artifact_mime'), 'size' => $this->integer($export, 'artifact_size'), 'checksum' => $this->string($export, 'artifact_checksum'), 'payload_checksum' => $this->string($export, 'payload_checksum')];
            $key = $this->deterministicKey($companyId, 'erp_export', $erpExportId, $version, $payload['checksum']);

            return $this->hub->queue($companyId, $connectionId, $actorId, 'erp_export', $erpExportId, 'erp.export.ready', $payload, $key);
        }, 5);
    }

    public function queueGovernmentFiling(int $companyId, int $filingId, int $connectionId, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $filingId, $connectionId, $actorId): int {
            $filing = $this->db->table('government_tax_filings')->where(['company_id' => $companyId, 'id' => $filingId])->whereIn('status', ['approved', 'submitted', 'accepted'])->lockForUpdate()->first();
            abort_unless(is_object($filing), 404);
            $document = $this->db->table('government_tax_filing_documents')->where(['company_id' => $companyId, 'government_tax_filing_id' => $filingId])->where('document_version', $this->integer($filing, 'version'))->lockForUpdate()->first();
            if (! is_object($document)) {
                throw ValidationException::withMessages(['filing' => 'Persisted Government XML artifact is required.']);
            }
            $this->verifyArtifact($document, 'storage_disk', 'storage_path', 'file_size', 'checksum');
            if (! hash_equals($this->string($filing, 'payload_checksum'), $this->string($document, 'checksum'))) {
                throw ValidationException::withMessages(['filing' => 'Government artifact checksum does not match its immutable filing.']);
            }
            $version = $this->integer($filing, 'version');
            $payload = ['source' => 'government_tax_filing', 'source_id' => $filingId, 'version' => $version, 'artifact_uuid' => $this->string($document, 'uuid'), 'artifact_name' => $this->string($document, 'file_name'), 'mime' => $this->string($document, 'mime_type'), 'size' => $this->integer($document, 'file_size'), 'checksum' => $this->string($document, 'checksum'), 'source_checksum' => $this->string($filing, 'source_checksum')];
            $key = $this->deterministicKey($companyId, 'government_tax_filing', $filingId, $version, $payload['checksum']);

            return $this->hub->queue($companyId, $connectionId, $actorId, 'government_tax_filing', $filingId, 'government.filing.ready', $payload, $key);
        }, 5);
    }

    private function verifyArtifact(object $row, string $diskColumn, string $pathColumn, string $sizeColumn, string $checksumColumn): void
    {
        $disk = $this->string($row, $diskColumn);
        $path = $this->string($row, $pathColumn);
        if ($disk !== 'local' || $path === '' || ! Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages(['artifact' => 'Persisted source artifact is unavailable.']);
        }
        $bytes = Storage::disk($disk)->get($path);
        if (! is_string($bytes) || strlen($bytes) !== $this->integer($row, $sizeColumn) || ! hash_equals($this->string($row, $checksumColumn), hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['artifact' => 'Persisted source artifact failed integrity validation.']);
        }
    }

    private function deterministicKey(int $companyId, string $source, int $id, int $version, string $checksum): string
    {
        $hash = IntegrationChecksum::make(['company_id' => $companyId, 'source' => $source, 'id' => $id, 'version' => $version, 'checksum' => $checksum]);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }

    private function integer(object $row, string $key): int
    {
        $value = $row->{$key} ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
