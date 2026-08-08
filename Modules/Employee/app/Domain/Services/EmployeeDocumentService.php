<?php

namespace Modules\Employee\Domain\Services;

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

final readonly class EmployeeDocumentService
{
    /** @var list<string> */
    private const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    public function __construct(private MalwareScannerInterface $scanner) {}

    public function upload(int $companyId, int $employeeId, DocumentClassification $type, string $name, UploadedFile $file, int $actorId): EmployeeDocument
    {
        Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
        $mime = $file->getMimeType();
        if (! is_string($mime) || ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['document' => 'Định dạng tài liệu không được phép.']);
        }
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw new RuntimeException('Không thể tính checksum tài liệu.');
        }
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'docx',
        };
        $storageKey = 'employees/'.$companyId.'/'.$employeeId.'/documents/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk('local')->putFileAs(dirname($storageKey), $file, basename($storageKey));
        if (! is_string($stored)) {
            throw new RuntimeException('Không thể lưu tài liệu riêng tư.');
        }
        $absolutePath = Storage::disk('local')->path($stored);
        $scanStatus = $this->scanner->scan($absolutePath);
        if ($scanStatus === DocumentScanStatus::Infected) {
            Storage::disk('local')->delete($stored);
            throw ValidationException::withMessages(['document' => 'Tài liệu bị phát hiện có mã độc.']);
        }

        return DB::transaction(function () use ($employeeId, $type, $name, $file, $mime, $checksum, $stored, $scanStatus, $actorId, $companyId): EmployeeDocument {
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
                'access_policy' => ['private' => true],
            ]);
            activity('employee-document')->event('uploaded')->withProperties(['company_id' => $companyId, 'employee_id' => $employeeId, 'document_id' => $document->id, 'version' => $version, 'actor_id' => $actorId])->log('Employee document uploaded');

            return $document;
        }, 3);
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

    public function archive(int $companyId, int $documentId): void
    {
        $document = $this->download($companyId, $documentId);
        if ($document->signed_at !== null) {
            throw ValidationException::withMessages(['document' => 'Không thể lưu trữ tài liệu đã ký.']);
        }
        $document->update(['archived_at' => now()]);
    }
}
