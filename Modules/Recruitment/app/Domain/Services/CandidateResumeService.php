<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Enums\DocumentScanStatus;
use Modules\Employee\Domain\Services\MalwareScannerInterface;
use Modules\Recruitment\Domain\Models\Candidate;
use RuntimeException;

final readonly class CandidateResumeService
{
    /** @var list<string> */
    private const MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(private MalwareScannerInterface $scanner) {}

    public function upload(int $companyId, int $candidateId, UploadedFile $file, int $actorId): void
    {
        $mime = $file->getMimeType();
        if (! is_string($mime) || ! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages(['resume' => 'Resume chỉ chấp nhận PDF, DOC hoặc DOCX hợp lệ.']);
        }
        $path = $file->getRealPath();
        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum)) {
            throw new RuntimeException('Không thể tính checksum resume.');
        }
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            default => 'docx',
        };
        $storageKey = 'recruitment/'.$companyId.'/candidates/'.$candidateId.'/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk('local')->putFileAs(dirname($storageKey), $file, basename($storageKey));
        if (! is_string($stored)) {
            throw new RuntimeException('Không thể lưu resume riêng tư.');
        }
        $scan = $this->scanner->scan(Storage::disk('local')->path($stored));
        if ($scan === DocumentScanStatus::Infected) {
            Storage::disk('local')->delete($stored);
            throw ValidationException::withMessages(['resume' => 'Resume bị phát hiện có mã độc.']);
        }

        $candidate = Candidate::query()->where('company_id', $companyId)->findOrFail($candidateId);
        $oldKey = $candidate->resume_storage_key;
        $candidate->update([
            'resume_url' => null,
            'resume_storage_key' => $stored,
            'resume_mime_type' => $mime,
            'resume_size' => $file->getSize(),
            'resume_checksum' => $checksum,
            'resume_scan_status' => $scan->value,
            'resume_version' => $candidate->resume_version + 1,
        ]);
        if (is_string($oldKey) && $oldKey !== '') {
            Storage::disk('local')->delete($oldKey);
        }
        activity('candidate-resume')->performedOn($candidate)->causedBy($actorId)->event('uploaded')
            ->withProperties(['company_id' => $companyId, 'candidate_id' => $candidateId, 'checksum' => $checksum, 'version' => $candidate->resume_version])
            ->log('Candidate resume uploaded');
    }

    public function downloadable(int $companyId, int $candidateId): Candidate
    {
        $candidate = Candidate::query()->where('company_id', $companyId)->findOrFail($candidateId);
        if (! is_string($candidate->resume_storage_key) || $candidate->resume_storage_key === '') {
            throw ValidationException::withMessages(['resume' => 'Ứng viên chưa có resume.']);
        }
        if (! in_array($candidate->resume_scan_status, [DocumentScanStatus::Clean->value, DocumentScanStatus::Skipped->value], true)) {
            throw ValidationException::withMessages(['resume' => 'Resume chưa vượt qua kiểm tra an toàn.']);
        }

        return $candidate;
    }

    public function remove(int $companyId, int $candidateId, int $actorId): void
    {
        $candidate = $this->downloadable($companyId, $candidateId);
        $key = $candidate->resume_storage_key;
        $candidate->update([
            'resume_storage_key' => null,
            'resume_mime_type' => null,
            'resume_size' => null,
            'resume_checksum' => null,
            'resume_scan_status' => null,
        ]);
        if (is_string($key)) {
            Storage::disk('local')->delete($key);
        }
        activity('candidate-resume')->performedOn($candidate)->causedBy($actorId)->event('removed')
            ->withProperties(['company_id' => $companyId, 'candidate_id' => $candidateId])->log('Candidate resume removed');
    }
}
