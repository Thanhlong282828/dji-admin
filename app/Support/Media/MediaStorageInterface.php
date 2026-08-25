<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;

interface MediaStorageInterface
{
    public function upload(UploadedFile $file, MediaPurpose $purpose): StoredMedia;

    public function delete(string $assetId): void;
}
