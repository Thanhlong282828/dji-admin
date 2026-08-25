<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class LocalMediaStorage implements MediaStorageInterface
{
    public function upload(UploadedFile $file, MediaPurpose $purpose): StoredMedia
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Loại media không được hỗ trợ.'),
        };
        $path = 'media/'.$purpose->value.'/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));
        if (! is_string($stored)) {
            throw new RuntimeException('Không thể lưu media local.');
        }

        return new StoredMedia('local', $stored, Storage::disk('public')->url($stored), (string) $file->getMimeType(), (int) $file->getSize(), MediaFileFingerprint::sha256($file));
    }

    public function delete(string $assetId): void
    {
        Storage::disk('public')->delete($assetId);
    }
}
