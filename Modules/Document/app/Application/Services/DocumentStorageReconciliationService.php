<?php

namespace Modules\Document\Application\Services;

use App\Support\Storage\DocumentStorageTelemetry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class DocumentStorageReconciliationService
{
    public function __construct(private ConnectionInterface $db, private DocumentStorageTelemetry $telemetry) {}

    /** @return array{company_id:int,dry_run:true,checked:int,anomalies:list<array{type:string,source:string,reference_hash:string}>,truncated:bool,duration_ms:int} */
    public function audit(int $companyId, int $limit = 500, int $maxSeconds = 30): array
    {
        $limit = max(1, min($this->configInteger('document.reconciliation.max_items', 500), $limit));
        $maxSeconds = max(1, min($this->configInteger('document.reconciliation.max_seconds', 30), $maxSeconds));
        $startedAt = hrtime(true);
        $deadline = microtime(true) + $maxSeconds;
        $anomalies = $this->emptyAnomalies();
        $references = $this->emptyReferences();
        $checked = 0;
        $truncated = false;

        $enterprise = $this->db->table('enterprise_document_versions as v')
            ->join('enterprise_documents as d', 'd.id', '=', 'v.document_id')
            ->where('d.company_id', $companyId)
            ->orderBy('v.id')
            ->limit($limit)
            ->get(['v.id', 'v.storage_driver', 'v.storage_path', 'v.sha256', 'v.size_bytes']);

        foreach ($enterprise as $row) {
            if ($this->expired($deadline, $checked, $limit)) {
                $truncated = true;
                break;
            }
            $driver = is_string($row->storage_driver ?? null) ? $row->storage_driver : '';
            $disk = $driver === 'local_private' ? 'local' : $driver;
            $path = is_string($row->storage_path ?? null) ? $row->storage_path : '';
            $this->inspectMetadata('enterprise', $this->integer($row->id ?? null), $disk, $path, $this->string($row->sha256 ?? null), $this->integer($row->size_bytes ?? null), $companyId, $references, $anomalies);
            $checked++;
        }

        if (! $truncated) {
            $remaining = max(0, $limit - $checked);
            $employee = $this->db->table('employee_documents as d')
                ->join('employees as e', 'e.id', '=', 'd.employee_id')
                ->where('e.company_id', $companyId)
                ->orderBy('d.id')
                ->limit($remaining)
                ->get(['d.id', 'd.storage_key', 'd.checksum', 'd.size_bytes', 'd.access_policy']);
            foreach ($employee as $row) {
                if ($this->expired($deadline, $checked, $limit)) {
                    $truncated = true;
                    break;
                }
                $policy = json_decode(is_string($row->access_policy ?? null) ? $row->access_policy : '{}', true);
                $disk = is_array($policy) && is_string($policy['storage_disk'] ?? null) ? $policy['storage_disk'] : 'local';
                $path = is_string($row->storage_key ?? null) ? $row->storage_key : '';
                $this->inspectMetadata('employee', $this->integer($row->id ?? null), $disk, $path, $this->string($row->checksum ?? null), $this->integer($row->size_bytes ?? null), $companyId, $references, $anomalies);
                $checked++;
            }
        }

        if (! $truncated && $checked < $limit) {
            foreach (['enterprise-documents/'.$companyId, 'employees/'.$companyId] as $prefix) {
                foreach (['local', 'supabase'] as $disk) {
                    try {
                        foreach (Storage::disk($disk)->getDriver()->listContents($prefix, true) as $attribute) {
                            if ($this->expired($deadline, $checked, $limit)) {
                                $truncated = true;
                                break 3;
                            }
                            if (! $attribute->isFile()) {
                                continue;
                            }
                            $path = $attribute->path();
                            if (! isset($references[$disk.'|'.$path])) {
                                $this->addAnomaly($anomalies, 'orphan_object', $disk, $path, $companyId);
                            }
                            $checked++;
                        }
                    } catch (Throwable $exception) {
                        $this->addAnomaly($anomalies, 'provider_scan_failed', $disk, $exception::class, $companyId);
                    }
                }
            }
        }

        return ['company_id' => $companyId, 'dry_run' => true, 'checked' => $checked, 'anomalies' => $anomalies, 'truncated' => $truncated, 'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000)];
    }

    /**
     * @param  array<string,int>  $references
     * @param  list<array{type:string,source:string,reference_hash:string}>  $anomalies
     */
    private function inspectMetadata(string $source, int $id, string $disk, string $path, string $checksum, int $size, int $companyId, array &$references, array &$anomalies): void
    {
        if (! in_array($disk, ['local', 'supabase'], true) || $path === '') {
            $this->addAnomaly($anomalies, 'wrong_provider_reference', $source, $source.':'.$id, $companyId);

            return;
        }
        $reference = $disk.'|'.$path;
        if (isset($references[$reference])) {
            $this->addAnomaly($anomalies, 'duplicate_reference', $source, $reference, $companyId);
        }
        $references[$reference] = $id;
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            $this->addAnomaly($anomalies, 'missing_object', $source, $reference, $companyId);

            return;
        }
        if ($size > 0 && $storage->size($path) !== $size) {
            $this->addAnomaly($anomalies, 'size_mismatch', $source, $reference, $companyId);

            return;
        }
        $contents = $checksum !== '' && $size <= 10 * 1024 * 1024 ? $storage->get($path) : null;
        if (is_string($contents) && hash('sha256', $contents) !== $checksum) {
            $this->addAnomaly($anomalies, 'checksum_mismatch', $source, $reference, $companyId);
        }
    }

    /** @param list<array{type:string,source:string,reference_hash:string}> $anomalies */
    private function addAnomaly(array &$anomalies, string $type, string $source, string $reference, int $companyId): void
    {
        $anomalies[] = ['type' => $type, 'source' => $source, 'reference_hash' => hash('sha256', $reference)];
        $this->telemetry->incrementAnomaly($type, $companyId);
    }

    private function expired(float $deadline, int $checked, int $limit): bool
    {
        return microtime(true) >= $deadline || $checked >= $limit;
    }

    private function configInteger(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @return list<array{type:string,source:string,reference_hash:string}> */
    private function emptyAnomalies(): array
    {
        return [];
    }

    /** @return array<string,int> */
    private function emptyReferences(): array
    {
        return [];
    }
}
