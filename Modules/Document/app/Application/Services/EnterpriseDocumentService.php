<?php

namespace Modules\Document\Application\Services;

use App\Support\Storage\DocumentStorageTelemetry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class EnterpriseDocumentService
{
    public function __construct(private ConnectionInterface $db, private DocumentMalwareScannerInterface $scanner, private DocumentStorageTelemetry $telemetry) {}

    /** @param array<string,mixed> $data */
    public function create(array $data, UploadedFile $file, int $actorId): int
    {
        $companyId = $this->integer($data['company_id'] ?? null);
        $diskName = $this->storageDisk();
        $id = 0;

        try {
            return $this->db->transaction(function () use ($data, $file, $actorId, $companyId, &$id): int {
                $subjectType = $this->string($data['subject_type'] ?? null);
                $this->validateSubject($companyId, $subjectType, $this->integer($data['subject_id'] ?? null));
                $id = (int) $this->db->table('enterprise_documents')->insertGetId(['company_id' => $companyId, 'document_type' => $data['document_type'], 'document_number' => $data['document_number'] ?? null, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'classification' => $data['classification'], 'owner_module' => $data['owner_module'], 'subject_type' => $data['subject_type'], 'subject_id' => $data['subject_id'], 'status' => 'ACTIVE', 'retention_until' => $data['retention_until'] ?? null, 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
                $this->upload($companyId, $id, $file, $actorId);
                activity('enterprise-document')->causedBy($actorId)->event('created')->withProperties(['company_id' => $companyId, 'document_id' => $id, 'owner_module' => $data['owner_module'], 'subject_type' => $data['subject_type'], 'subject_id' => $data['subject_id']])->log('Enterprise document created');

                return $id;
            });
        } catch (Throwable $exception) {
            if ($id > 0) {
                $this->cleanupDirectory($diskName, "enterprise-documents/{$companyId}/{$id}", $companyId);
            }

            throw $exception;
        }
    }

    public function upload(int $companyId, int $documentId, UploadedFile $file, int $actorId): int
    {
        $diskName = $this->storageDisk();
        [$scanStatus, $mime, $extension] = $this->validateUpload($file, $diskName, $companyId);

        return $this->db->transaction(function () use ($companyId, $documentId, $file, $actorId, $scanStatus, $mime, $extension, $diskName): int {
            $document = $this->db->table('enterprise_documents')->where(['id' => $documentId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($document !== null, 404);
            abort_if(($document->status ?? null) === 'ARCHIVED', 409, 'Tài liệu đã lưu trữ.');
            $latestVersion = $this->db->table('enterprise_document_versions')->where('document_id', $documentId)->max('version_number');
            $version = $this->integer($latestVersion) + 1;
            $realPath = $file->getRealPath();
            abort_unless(is_string($realPath), 422);
            $sha = hash_file('sha256', $realPath);
            if (! is_string($sha)) {
                throw new RuntimeException('Không thể tính SHA-256.');
            }
            $name = Str::uuid().'.'.$extension;
            $directory = "enterprise-documents/{$companyId}/{$documentId}";
            $expectedPath = $directory.'/'.$name;
            abort_if(Storage::disk($diskName)->exists($expectedPath), 409, 'Khóa lưu trữ tài liệu đã tồn tại.');
            $startedAt = hrtime(true);
            try {
                $path = Storage::disk($diskName)->putFileAs($directory, $file, $name, ['ContentType' => $mime]);
                $this->telemetry->record('upload', $diskName, $companyId, true, $this->elapsedMs($startedAt));
            } catch (Throwable $exception) {
                $this->telemetry->record('upload', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
                throw $exception;
            }
            abort_unless(is_string($path), 500);
            try {
                $id = (int) $this->db->table('enterprise_document_versions')->insertGetId(['document_id' => $documentId, 'version_number' => $version, 'storage_driver' => $diskName === 'local' ? 'local_private' : $diskName, 'storage_path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $mime, 'size_bytes' => $file->getSize(), 'sha256' => $sha, 'uploaded_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
                $this->db->table('enterprise_documents')->where('id', $documentId)->update(['current_version_id' => $id, 'updated_by' => $actorId, 'updated_at' => now()]);
                activity('enterprise-document')->causedBy($actorId)->event('version_uploaded')->withProperties(['company_id' => $companyId, 'document_id' => $documentId, 'version_id' => $id, 'version' => $version, 'sha256' => $sha, 'storage_driver' => $diskName, 'scan_status' => $scanStatus->value])->log('Enterprise document version uploaded');
            } catch (Throwable $exception) {
                $this->cleanupObject($diskName, $path, $companyId);
                throw $exception;
            }

            return $id;
        });
    }

    public function archive(int $companyId, int $documentId, int $actorId): void
    {
        $updated = $this->db->table('enterprise_documents')->where(['id' => $documentId, 'company_id' => $companyId, 'status' => 'ACTIVE'])->update(['status' => 'ARCHIVED', 'updated_by' => $actorId, 'updated_at' => now()]);
        abort_if($updated === 0, 404);
        activity('enterprise-document')->causedBy($actorId)->event('archived')->withProperties(['company_id' => $companyId, 'document_id' => $documentId])->log('Enterprise document archived');
    }

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $documentId = null): array
    {
        $query = $this->db->table('enterprise_documents as d')->leftJoin('enterprise_document_versions as v', 'v.id', '=', 'd.current_version_id')->where('d.company_id', $companyId)->select('d.*', 'v.version_number', 'v.original_name', 'v.mime_type', 'v.size_bytes', 'v.sha256');
        if ($documentId !== null) {
            $document = (clone $query)->where('d.id', $documentId)->first();
            abort_unless($document !== null, 404);

            return ['document' => $document, 'versions' => $this->db->table('enterprise_document_versions')->where('document_id', $documentId)->orderByDesc('version_number')->get(['id', 'version_number', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by', 'created_at'])];
        }

        return ['documents' => $query->orderByDesc('d.updated_at')->paginate(30)];
    }

    /** @return array{disk:string,path:string,name:string,mime:string,temporary_url:?string} */
    public function download(int $companyId, int $versionId): array
    {
        $row = $this->db->table('enterprise_document_versions as v')->join('enterprise_documents as d', 'd.id', '=', 'v.document_id')->where(['d.company_id' => $companyId, 'v.id' => $versionId])->first(['v.storage_driver', 'v.storage_path', 'v.original_name', 'v.mime_type']);
        abort_unless($row !== null, 404);
        $driver = is_string($row->storage_driver ?? null) ? $row->storage_driver : '';
        $diskName = $driver === 'local_private' ? 'local' : $driver;
        abort_unless(in_array($diskName, ['local', 'supabase'], true), 404);
        $path = is_string($row->storage_path ?? null) ? $row->storage_path : '';
        $startedAt = hrtime(true);
        try {
            $exists = Storage::disk($diskName)->exists($path);
            $this->telemetry->record('head', $diskName, $companyId, $exists, $this->elapsedMs($startedAt), null, $exists ? null : 404);
        } catch (Throwable $exception) {
            $this->telemetry->record('head', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
            throw $exception;
        }
        abort_unless($exists, 404);
        $temporaryUrl = null;
        if ($diskName !== 'local') {
            $configuredMinutes = config('document.temporary_url_minutes', 5);
            $minutes = is_int($configuredMinutes) ? max(1, min(60, $configuredMinutes)) : 5;
            $originalName = is_string($row->original_name ?? null) ? $row->original_name : 'document';
            $startedAt = hrtime(true);
            try {
                $temporaryUrl = Storage::disk($diskName)->temporaryUrl($path, now()->addMinutes($minutes), [
                    'ResponseContentDisposition' => 'attachment; filename="'.str_replace(['"', "\r", "\n"], '', basename($originalName)).'"',
                    'ResponseContentType' => is_string($row->mime_type ?? null) ? $row->mime_type : 'application/octet-stream',
                    'ResponseCacheControl' => 'private, no-store',
                ]);
                $this->telemetry->record('signed_url', $diskName, $companyId, true, $this->elapsedMs($startedAt));
            } catch (Throwable $exception) {
                $this->telemetry->record('signed_url', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
                throw $exception;
            }
        }

        return ['disk' => $diskName, 'path' => $path, 'name' => is_string($row->original_name ?? null) ? $row->original_name : 'document', 'mime' => is_string($row->mime_type ?? null) ? $row->mime_type : 'application/octet-stream', 'temporary_url' => $temporaryUrl];
    }

    private function storageDisk(): string
    {
        $disk = config('document.storage_disk', 'local');
        if (! is_string($disk) || ! in_array($disk, ['local', 'supabase'], true)) {
            throw new RuntimeException('Document storage provider không hợp lệ.');
        }
        if ($disk === 'supabase') {
            foreach (['key', 'secret', 'bucket', 'endpoint'] as $key) {
                $value = config("filesystems.disks.supabase.{$key}");
                if (! is_string($value) || trim($value) === '') {
                    throw new RuntimeException('Supabase Storage chưa được cấu hình đầy đủ.');
                }
            }
        }

        return $disk;
    }

    /** @return array{DocumentScanStatus, string, string} */
    private function validateUpload(UploadedFile $file, string $diskName, int $companyId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $realPath = $file->getRealPath();
        abort_unless(is_string($realPath), 422, 'Không đọc được file upload.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        $signatures = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'csv' => ['text/csv', 'text/plain'],
            'txt' => ['text/plain'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];
        abort_unless(is_string($mime) && isset($signatures[$extension]) && in_array($mime, $signatures[$extension], true), 422, 'Loại hoặc chữ ký file không được hỗ trợ.');
        if ($diskName === 'supabase') {
            abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 422, 'Định dạng file không nằm trong MIME allowlist của Supabase Storage.');
        }
        abort_if($file->getSize() < 1 || $file->getSize() > 10 * 1024 * 1024, 422, 'Kích thước file không hợp lệ.');
        $startedAt = hrtime(true);
        $scanStatus = $this->scanner->scan($realPath);
        $this->telemetry->record('scanner', 'clamav', $companyId, $scanStatus !== DocumentScanStatus::Failed, $this->elapsedMs($startedAt), null, $scanStatus === DocumentScanStatus::Failed ? 503 : null);
        abort_if($scanStatus === DocumentScanStatus::Infected, 422, 'Tài liệu bị phát hiện có mã độc.');
        abort_if($scanStatus === DocumentScanStatus::Failed, 503, 'Không thể kiểm tra an toàn tài liệu.');

        return [$scanStatus, $mime, $extension === 'jpeg' ? 'jpg' : $extension];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateSubject(int $companyId, string $type, int $id): void
    {
        $tables = ['EMPLOYEE' => 'employees', 'EMPLOYEE_CONTRACT' => 'employment_contracts', 'PURCHASE_ORDER' => 'purchase_orders', 'GOODS_RECEIPT' => 'procurement_goods_receipts', 'SERVICE_ACCEPTANCE' => 'service_acceptances', 'VENDOR_INVOICE' => 'vendor_bills', 'EXPENSE' => 'finance_expenses', 'ASSET' => 'finance_assets', 'SALES_ORDER' => 'sales_orders', 'CUSTOMER_INVOICE' => 'customer_invoices', 'ACCOUNTING_ADJUSTMENT' => 'accounting_adjustment_requests', 'PROJECT' => 'projects', 'PROJECT_MILESTONE' => 'project_milestones', 'PROJECT_TASK' => 'project_tasks', 'MAINTENANCE_REQUEST' => 'maintenance_requests', 'MAINTENANCE_WORK_ORDER' => 'maintenance_work_orders', 'MAINTENANCE_PLAN' => 'preventive_maintenance_plans'];
        $table = $tables[$type] ?? null;
        abort_unless(is_string($table), 422, 'Loại liên kết không được hỗ trợ.');
        if ($type === 'EMPLOYEE_CONTRACT') {
            $exists = $this->db->table('employment_contracts as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where(['c.id' => $id, 'e.company_id' => $companyId])->exists();
        } elseif (in_array($type, ['PROJECT_MILESTONE', 'PROJECT_TASK'], true)) {
            $exists = $this->db->table($table.' as subject')->join('projects as p', 'p.id', '=', 'subject.project_id')->where(['subject.id' => $id, 'p.company_id' => $companyId])->exists();
        } else {
            $exists = $this->db->table($table)->where(['id' => $id, 'company_id' => $companyId])->exists();
        }
        abort_unless($exists, 404);
    }

    private function cleanupObject(string $diskName, string $path, int $companyId): void
    {
        $startedAt = hrtime(true);
        try {
            $deleted = Storage::disk($diskName)->delete($path);
            $this->telemetry->record('cleanup', $diskName, $companyId, $deleted, $this->elapsedMs($startedAt), null, $deleted ? null : 500);
        } catch (Throwable $exception) {
            $this->telemetry->record('cleanup', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
        }
    }

    private function cleanupDirectory(string $diskName, string $path, int $companyId): void
    {
        $startedAt = hrtime(true);
        try {
            $deleted = Storage::disk($diskName)->deleteDirectory($path);
            $this->telemetry->record('cleanup_directory', $diskName, $companyId, $deleted, $this->elapsedMs($startedAt), null, $deleted ? null : 500);
        } catch (Throwable $exception) {
            $this->telemetry->record('cleanup_directory', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
        }
    }

    private function elapsedMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }
}
