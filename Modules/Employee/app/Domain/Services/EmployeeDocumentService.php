<?php

namespace Modules\Employee\Domain\Services;

use App\Support\Storage\DocumentStorageTelemetry;
use App\Support\Storage\DocumentUploadPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Enums\DocumentClassification;
use Modules\Employee\Domain\Enums\DocumentScanStatus;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeDocument;
use RuntimeException;
use Throwable;

final readonly class EmployeeDocumentService
{
    public function __construct(private MalwareScannerInterface $scanner, private DocumentStorageTelemetry $telemetry, private DocumentUploadPolicy $uploadPolicy) {}

    public function upload(int $companyId, int $employeeId, DocumentClassification $type, string $name, UploadedFile $file, int $actorId): EmployeeDocument
    {
        Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
        $validated = $this->uploadPolicy->validate($file);
        $temporaryPath = $validated['path'];
        $mime = $validated['mime'];
        $checksum = hash_file('sha256', $temporaryPath);
        if (! is_string($checksum)) {
            throw new RuntimeException('Không thể tính checksum tài liệu.');
        }
        $extension = $validated['extension'];
        $startedAt = hrtime(true);
        $scanStatus = $this->scanner->scan($temporaryPath);
        $this->telemetry->record('scanner', 'clamav', $companyId, $scanStatus !== DocumentScanStatus::Failed, $this->elapsedMs($startedAt), null, $scanStatus === DocumentScanStatus::Failed ? 503 : null);
        if ($scanStatus === DocumentScanStatus::Infected) {
            throw ValidationException::withMessages(['document' => 'Tài liệu bị phát hiện có mã độc.']);
        }
        abort_if($scanStatus === DocumentScanStatus::Failed, 503, 'Không thể kiểm tra an toàn tài liệu.');

        $diskName = $this->configuredDisk();
        $storageKey = 'employees/'.$companyId.'/'.$employeeId.'/documents/'.Str::uuid().'.'.$extension;
        if (Storage::disk($diskName)->exists($storageKey)) {
            throw ValidationException::withMessages(['document' => 'Khóa lưu trữ tài liệu đã tồn tại.']);
        }
        $startedAt = hrtime(true);
        try {
            $stored = Storage::disk($diskName)->putFileAs(dirname($storageKey), $file, basename($storageKey), ['ContentType' => $mime]);
            $this->telemetry->record('upload', $diskName, $companyId, true, $this->elapsedMs($startedAt));
        } catch (Throwable $exception) {
            $this->telemetry->record('upload', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
            throw $exception;
        }
        if (! is_string($stored)) {
            throw new RuntimeException('Không thể lưu tài liệu riêng tư.');
        }

        try {
            return DB::transaction(function () use ($employeeId, $type, $name, $file, $mime, $checksum, $stored, $scanStatus, $actorId, $companyId, $diskName): EmployeeDocument {
                $latest = EmployeeDocument::query()
                    ->where('employee_id', $employeeId)
                    ->where('type', $type->value)
                    ->where('name', $name)
                    ->lockForUpdate()
                    ->max('version');
                $version = is_numeric($latest) ? (int) $latest + 1 : 1;
                $document = EmployeeDocument::query()->create([
                    'employee_id' => $employeeId,
                    'type' => $type->value,
                    'name' => $name,
                    'version' => $version,
                    'storage_key' => $stored,
                    'checksum' => $checksum,
                    'mime_type' => $mime,
                    'size_bytes' => $file->getSize(),
                    'scan_status' => $scanStatus->value,
                    'access_policy' => ['private' => true, 'storage_disk' => $diskName],
                ]);
                activity('employee-document')->event('uploaded')->withProperties(['company_id' => $companyId, 'employee_id' => $employeeId, 'document_id' => $document->id, 'version' => $version, 'actor_id' => $actorId, 'storage_disk' => $diskName])->log('Employee document uploaded');

                return $document;
            }, 3);
        } catch (Throwable $exception) {
            $this->cleanupObject($diskName, $stored, $companyId);

            throw $exception;
        }
    }

    public function download(int $companyId, int $documentId): EmployeeDocument
    {
        $document = EmployeeDocument::query()
            ->whereHas('employee', fn ($query) => $query->where('company_id', $companyId))
            ->whereNull('archived_at')
            ->findOrFail($documentId);
        if (! in_array($document->scan_status, [DocumentScanStatus::Clean->value, DocumentScanStatus::Skipped->value], true)) {
            throw ValidationException::withMessages(['document' => 'Tài liệu chưa vượt qua kiểm tra an toàn.']);
        }

        return $document;
    }

    /** @return array{disk: string, path: string, temporary_url: ?string} */
    public function downloadLocation(EmployeeDocument $document): array
    {
        $rawPolicy = $document->getAttribute('access_policy');
        $policy = is_array($rawPolicy) ? $rawPolicy : [];
        $recordedDisk = $policy['storage_disk'] ?? null;
        /** @var list<string> $candidates */
        $candidates = [];
        $sources = is_string($recordedDisk) ? [$recordedDisk] : [$this->configuredDisk(), 'local'];
        foreach ($sources as $candidate) {
            if (in_array($candidate, ['local', 'supabase'], true) && ! in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        foreach ($candidates as $diskName) {
            $disk = Storage::disk($diskName);
            $companyValue = $document->employee()->value('company_id');
            $companyId = is_numeric($companyValue) ? (int) $companyValue : 0;
            $startedAt = hrtime(true);
            try {
                $exists = $disk->exists($document->storage_key);
                $this->telemetry->record('head', $diskName, $companyId, $exists, $this->elapsedMs($startedAt), null, $exists ? null : 404);
            } catch (Throwable $exception) {
                $this->telemetry->record('head', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
                throw $exception;
            }
            if (! $exists) {
                continue;
            }
            if ($diskName === 'local') {
                return ['disk' => $diskName, 'path' => $document->storage_key, 'temporary_url' => null];
            }

            $downloadName = $document->name !== '' ? basename($document->name) : 'employee-document';
            $downloadName = str_replace(['"', "\r", "\n"], '', $downloadName);
            $mime = is_string($document->mime_type) ? $document->mime_type : 'application/octet-stream';
            $configuredMinutes = config('employee.document_temporary_url_minutes', 5);
            $minutes = is_int($configuredMinutes) ? max(1, min(60, $configuredMinutes)) : 5;

            $startedAt = hrtime(true);
            try {
                $temporaryUrl = $disk->temporaryUrl(
                    $document->storage_key,
                    now()->addMinutes($minutes),
                    ['ResponseContentDisposition' => 'attachment; filename="'.$downloadName.'"', 'ResponseContentType' => $mime, 'ResponseCacheControl' => 'private, no-store'],
                );
                $this->telemetry->record('signed_url', $diskName, $companyId, true, $this->elapsedMs($startedAt));
            } catch (Throwable $exception) {
                $this->telemetry->record('signed_url', $diskName, $companyId, false, $this->elapsedMs($startedAt), $exception);
                throw $exception;
            }

            return ['disk' => $diskName, 'path' => $document->storage_key, 'temporary_url' => $temporaryUrl];
        }

        abort(404, 'Tài liệu không tồn tại trong kho lưu trữ.');
    }

    public function archive(int $companyId, int $documentId): void
    {
        $document = $this->download($companyId, $documentId);
        if ($document->signed_at !== null) {
            throw ValidationException::withMessages(['document' => 'Không thể lưu trữ tài liệu đã ký.']);
        }
        $document->update(['archived_at' => now()]);
    }

    private function configuredDisk(): string
    {
        $disk = config('employee.document_disk', 'local');
        if (! is_string($disk) || ! in_array($disk, ['local', 'supabase'], true)) {
            throw new RuntimeException('Kho lưu trữ hồ sơ nhân viên không hợp lệ.');
        }
        if ($disk === 'supabase') {
            foreach (['key', 'secret', 'bucket', 'endpoint'] as $field) {
                if (! is_string(config('filesystems.disks.supabase.'.$field)) || config('filesystems.disks.supabase.'.$field) === '') {
                    throw new RuntimeException('Cấu hình Supabase Storage chưa đầy đủ.');
                }
            }
        }

        return $disk;
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

    private function elapsedMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }
}
