<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class MediaFileFingerprint
{
    public static function sha256(UploadedFile $file): string
    {
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            throw new RuntimeException('Không thể tạo checksum cho media.');
        }

        return $checksum;
    }
}
