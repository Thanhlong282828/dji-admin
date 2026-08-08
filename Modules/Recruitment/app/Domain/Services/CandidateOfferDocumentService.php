<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Enums\DocumentScanStatus;
use Modules\Employee\Domain\Services\MalwareScannerInterface;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use RuntimeException;

final readonly class CandidateOfferDocumentService
{
    public function __construct(private MalwareScannerInterface $scanner) {}

    public function upload(int $companyId, int $offerId, UploadedFile $file, int $actorId): void
    {
        $mime = $file->getMimeType();
        if ($mime !== 'application/pdf') {
            throw ValidationException::withMessages(['document' => 'Tài liệu Offer phải là PDF hợp lệ.']);
        }
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw new RuntimeException('Không thể tính checksum tài liệu Offer.');
        }
        $key = 'recruitment/'.$companyId.'/offers/'.$offerId.'/'.Str::uuid().'.pdf';
        $stored = Storage::disk('local')->putFileAs(dirname($key), $file, basename($key));
        if (! is_string($stored)) {
            throw new RuntimeException('Không thể lưu tài liệu Offer.');
        }
        $scan = $this->scanner->scan(Storage::disk('local')->path($stored));
        if ($scan === DocumentScanStatus::Infected) {
            Storage::disk('local')->delete($stored);
            throw ValidationException::withMessages(['document' => 'Tài liệu Offer bị phát hiện có mã độc.']);
        }
        $offer = CandidateOffer::query()->where('company_id', $companyId)->findOrFail($offerId);
        $old = $offer->document_storage_key;
        $offer->update(['document_storage_key' => $stored, 'document_mime_type' => $mime, 'document_size' => $file->getSize(), 'document_checksum' => $checksum, 'scan_status' => $scan->value]);
        if (is_string($old) && $old !== '') {
            Storage::disk('local')->delete($old);
        }
        activity('candidate-offer-document')->performedOn($offer)->causedBy($actorId)->event('uploaded')
            ->withProperties(['company_id' => $companyId, 'offer_id' => $offerId, 'checksum' => $checksum])->log('Candidate offer document uploaded');
    }

    public function downloadable(int $companyId, int $offerId): CandidateOffer
    {
        $offer = CandidateOffer::query()->where('company_id', $companyId)->findOrFail($offerId);
        if (! is_string($offer->document_storage_key) || $offer->document_storage_key === '') {
            throw ValidationException::withMessages(['document' => 'Offer chưa có tài liệu.']);
        }
        if (! in_array($offer->scan_status, [DocumentScanStatus::Clean->value, DocumentScanStatus::Skipped->value], true)) {
            throw ValidationException::withMessages(['document' => 'Tài liệu Offer chưa vượt qua kiểm tra an toàn.']);
        }

        return $offer;
    }

    public function remove(int $companyId, int $offerId, int $actorId): void
    {
        $offer = $this->downloadable($companyId, $offerId);
        if ($offer->status->value === 'accepted') {
            throw ValidationException::withMessages(['document' => 'Không thể gỡ tài liệu của Offer đã accepted.']);
        }
        $key = $offer->document_storage_key;
        $offer->update(['document_storage_key' => null, 'document_mime_type' => null, 'document_size' => null, 'document_checksum' => null, 'scan_status' => null]);
        if (is_string($key)) {
            Storage::disk('local')->delete($key);
        }
        activity('candidate-offer-document')->performedOn($offer)->causedBy($actorId)->event('removed')
            ->withProperties(['company_id' => $companyId, 'offer_id' => $offerId])->log('Candidate offer document removed');
    }
}
