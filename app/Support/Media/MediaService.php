<?php

namespace App\Support\Media;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class MediaService
{
    public function __construct(private MediaStorageInterface $storage, private DatabaseManager $db, private MediaImageInspector $inspector = new MediaImageInspector) {}

    public function stage(UploadedFile $file, MediaPurpose $purpose): StoredMedia
    {
        $this->validate($file, $purpose);

        return $this->storage->upload($file, $purpose);
    }

    public function attach(int|string|null $companyId, string $ownerType, int $ownerId, MediaPurpose $purpose, StoredMedia $media, int $sortOrder = 0): int
    {
        return (int) $this->db->table('media_assets')->insertGetId([
            'company_id' => $companyId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'purpose' => $purpose->value,
            'provider' => $media->provider,
            'provider_asset_id' => $media->assetId,
            'delivery_url' => $media->deliveryUrl,
            'mime_type' => $media->mimeType,
            'size_bytes' => $media->sizeBytes,
            'checksum' => $media->checksum,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function compensate(StoredMedia $media): void
    {
        $this->deleteSafely($media->assetId, 'upload_compensation');
    }

    public function removeAsset(int $assetId): void
    {
        $row = $this->db->table('media_assets')->where('id', $assetId)->first();
        if ($row === null) {
            return;
        }
        $reference = is_string($row->provider_asset_id ?? null) ? $row->provider_asset_id : '';
        $this->db->transaction(fn () => $this->db->table('media_assets')->where('id', $assetId)->delete(), 3);
        if ($reference !== '' && ! $this->db->table('media_assets')->where('provider', $row->provider)->where('provider_asset_id', $reference)->exists()) {
            $this->deleteSafely($reference, 'owned_media_cleanup');
        }
    }

    /** @param list<int> $assetIds */
    public function removeOwned(string $ownerType, int $ownerId, array $assetIds = []): void
    {
        $query = $this->db->table('media_assets')->where('owner_type', $ownerType)->where('owner_id', $ownerId);
        if ($assetIds !== []) {
            $query->whereIn('id', $assetIds);
        }
        foreach ($query->pluck('id') as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $this->removeAsset((int) $id);
            }
        }
    }

    private function validate(UploadedFile $file, MediaPurpose $purpose): void
    {
        [$maxBytes, $maxDimension] = match ($purpose) {
            MediaPurpose::ArticleCover, MediaPurpose::ArticleImage, MediaPurpose::StoreBanner => [10 * 1024 * 1024, 8192],
            MediaPurpose::ProductCover, MediaPurpose::ProductImage, MediaPurpose::ProductVariant => [10 * 1024 * 1024, 6000],
            default => [5 * 1024 * 1024, 6000],
        };
        $this->inspector->inspect($file, $maxBytes, $maxDimension);
    }

    private function deleteSafely(string $reference, string $operation): void
    {
        try {
            $this->storage->delete($reference);
        } catch (Throwable $exception) {
            Log::warning('Media orphan cleanup deferred.', ['component' => 'media', 'operation' => $operation, 'reference_hash' => hash('sha256', $reference), 'exception' => $exception::class]);
        }
    }
}
