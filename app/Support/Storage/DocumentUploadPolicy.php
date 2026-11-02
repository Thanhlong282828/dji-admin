<?php

namespace App\Support\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class DocumentUploadPolicy
{
    /** @var array<string, list<string>> */
    private const MIMES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv' => ['text/csv', 'text/plain'],
        'txt' => ['text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    /** @return array{mime:string, extension:string, path:string} */
    public function validate(UploadedFile $file, bool $allowPlainText = false): array
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());
        if (! is_string($path) || $file->getSize() < 1 || $file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['document' => 'Kích thước tài liệu không hợp lệ.']);
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($detected) || ! isset(self::MIMES[$extension]) || ! in_array($detected, self::MIMES[$extension], true)) {
            throw ValidationException::withMessages(['document' => 'Loại hoặc chữ ký file không được hỗ trợ.']);
        }
        if (! $allowPlainText && in_array($extension, ['csv', 'txt'], true)) {
            throw ValidationException::withMessages(['document' => 'Định dạng tài liệu không được phép cho kho lưu trữ này.']);
        }

        $mime = match ($extension) {
            'pdf' => $this->assertPrefix($path, '%PDF-', 'application/pdf'),
            'doc' => $this->assertOle($path, 'application/msword'),
            'xls' => $this->assertOle($path, 'application/vnd.ms-excel'),
            'docx' => $this->assertOoxml($path, 'word/', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'xlsx' => $this->assertOoxml($path, 'xl/', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            default => $detected,
        };

        return ['mime' => $mime, 'extension' => $extension === 'jpeg' ? 'jpg' : $extension, 'path' => $path];
    }

    private function assertPrefix(string $path, string $prefix, string $mime): string
    {
        $length = strlen($prefix);
        if ($length === 0) {
            throw ValidationException::withMessages(['document' => 'Chữ ký file không hợp lệ.']);
        }
        $stream = fopen($path, 'rb');
        $header = is_resource($stream) ? fread($stream, $length) : false;
        if (is_resource($stream)) {
            fclose($stream);
        }
        if ($header !== $prefix) {
            throw ValidationException::withMessages(['document' => 'Chữ ký file không khớp với phần mở rộng.']);
        }

        return $mime;
    }

    private function assertOle(string $path, string $mime): string
    {
        $stream = fopen($path, 'rb');
        $header = is_resource($stream) ? fread($stream, 8) : false;
        if (is_resource($stream)) {
            fclose($stream);
        }
        if ($header !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            throw ValidationException::withMessages(['document' => 'Chữ ký OLE không khớp với tài liệu Word/Excel.']);
        }

        return $mime;
    }

    private function assertOoxml(string $path, string $directory, string $mime): string
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw ValidationException::withMessages(['document' => 'Tài liệu OOXML không hợp lệ.']);
        }
        try {
            $valid = $archive->locateName('[Content_Types].xml') !== false;
            for ($index = 0; $valid && $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if (is_string($name) && str_starts_with($name, $directory)) {
                    return $mime;
                }
            }
        } finally {
            $archive->close();
        }

        throw ValidationException::withMessages(['document' => 'Cấu trúc OOXML không khớp với phần mở rộng.']);
    }
}
