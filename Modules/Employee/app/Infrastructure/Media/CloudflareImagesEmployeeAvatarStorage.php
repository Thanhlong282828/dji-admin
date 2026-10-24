<?php

namespace Modules\Employee\Infrastructure\Media;

use App\Support\Media\CloudflareImagesStorage;
use App\Support\Media\MediaPurpose;
use App\Support\Secrets\SecretProviderInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Modules\Employee\Domain\Services\EmployeeAvatarStorageInterface;
use Modules\Employee\Domain\Services\StoredEmployeeAvatar;

final readonly class CloudflareImagesEmployeeAvatarStorage implements EmployeeAvatarStorageInterface
{
    private CloudflareImagesStorage $storage;

    public function __construct(Factory $http, SecretProviderInterface $secrets)
    {
        $this->storage = new CloudflareImagesStorage($http, $secrets);
    }

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
