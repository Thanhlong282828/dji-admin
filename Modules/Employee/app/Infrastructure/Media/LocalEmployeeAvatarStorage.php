<?php

namespace Modules\Employee\Infrastructure\Media;

use App\Support\Media\LocalMediaStorage;
use App\Support\Media\MediaPurpose;
use Illuminate\Http\UploadedFile;
use Modules\Employee\Domain\Services\EmployeeAvatarStorageInterface;
use Modules\Employee\Domain\Services\StoredEmployeeAvatar;

final readonly class LocalEmployeeAvatarStorage implements EmployeeAvatarStorageInterface
{
    public function __construct(private LocalMediaStorage $storage) {}

    public function upload(UploadedFile $file): StoredEmployeeAvatar
    {
        $media = $this->storage->upload($file, MediaPurpose::EmployeeAvatar);

        return new StoredEmployeeAvatar($media->assetId, $media->deliveryUrl);
    }

    public function delete(string $reference): void
    {
        $this->storage->delete($reference);
    }
}
