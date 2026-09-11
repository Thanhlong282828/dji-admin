<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Imagick;

final class MediaImageInspector
{
    /** @return array{mime:string,width:int,height:int} */
    public function inspect(UploadedFile $file, int $maxBytes, int $maxDimension): array
    {
        $size = $file->getSize();
        if (! is_int($size) || $size < 1 || $size > $maxBytes || ! $file->isValid()) {
            $this->invalid();
        }

        $path = $file->getRealPath();
        $mime = $file->getMimeType();
        $dimensions = getimagesize($path);
        if (! is_string($mime) || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $dimensions === false) {
            $this->invalid();
        }

        $width = $dimensions[0];
        $height = $dimensions[1];
        if ($width < 1 || $height < 1 || $width > $maxDimension || $height > $maxDimension || $height > intdiv(67_108_864, $width)) {
            $this->invalid();
        }

        $bytes = file_get_contents($path);
        if (! is_string($bytes) || ! $this->canDecode($bytes)) {
            $this->invalid();
        }

        return ['mime' => $mime, 'width' => $width, 'height' => $height];
    }

    private function canDecode(string $bytes): bool
    {
        if (function_exists('imagecreatefromstring')) {
            $decoded = @imagecreatefromstring($bytes);
            if ($decoded === false) {
                return false;
            }
            imagedestroy($decoded);

            return true;
        }

        if (class_exists(Imagick::class)) {
            try {
                $image = new Imagick;
                $image->readImageBlob($bytes);
                $valid = $image->getNumberImages() === 1;
                $image->clear();

                return $valid;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /** @return never */
    private function invalid(): void
    {
        throw ValidationException::withMessages(['media' => 'Media không phải ảnh JPEG/PNG/WebP hợp lệ hoặc vượt giới hạn.']);
    }
}
