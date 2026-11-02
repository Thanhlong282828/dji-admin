<?php

namespace Tests\Unit\Storage;

require_once dirname(__DIR__, 3).'/app/Support/Storage/DocumentUploadPolicy.php';

use App\Support\Storage\DocumentUploadPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

final class DocumentUploadPolicyTest extends TestCase
{
    #[DataProvider('officeDocuments')]
    public function test_it_accepts_real_office_containers_and_normalizes_content_type(string $extension, string $directory, string $expectedMime): void
    {
        $path = $this->ooxml($directory);
        try {
            $result = app(DocumentUploadPolicy::class)->validate(new UploadedFile($path, 'evidence.'.$extension, null, null, true));
            self::assertSame($extension, $result['extension']);
            self::assertSame($expectedMime, $result['mime']);
        } finally {
            @unlink($path);
        }
    }

    /** @return iterable<string, array{string,string,string}> */
    public static function officeDocuments(): iterable
    {
        yield 'docx' => ['docx', 'word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        yield 'xlsx' => ['xlsx', 'xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }

    public function test_it_rejects_arbitrary_zip_renamed_as_office_document(): void
    {
        $path = $this->ooxml('payload.bin', false);
        try {
            $this->expectException(ValidationException::class);
            app(DocumentUploadPolicy::class)->validate(new UploadedFile($path, 'forged.docx', null, null, true));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_accepts_pdf_signature_and_rejects_extension_mismatch(): void
    {
        $valid = tempnam(sys_get_temp_dir(), 'pdf-');
        $invalid = tempnam(sys_get_temp_dir(), 'pdf-');
        self::assertIsString($valid);
        self::assertIsString($invalid);
        file_put_contents($valid, "%PDF-1.7\nfixture");
        file_put_contents($invalid, 'MZ executable');
        try {
            self::assertSame('application/pdf', app(DocumentUploadPolicy::class)->validate(new UploadedFile($valid, 'evidence.pdf', null, null, true))['mime']);
            $this->expectException(ValidationException::class);
            app(DocumentUploadPolicy::class)->validate(new UploadedFile($invalid, 'evidence.pdf', null, null, true));
        } finally {
            @unlink($valid);
            @unlink($invalid);
        }
    }

    private function ooxml(string $entry, bool $includeContentTypes = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ooxml-');
        self::assertIsString($path);
        $archive = new ZipArchive;
        self::assertTrue($archive->open($path, ZipArchive::OVERWRITE) === true);
        if ($includeContentTypes) {
            $archive->addFromString('[Content_Types].xml', '<Types/>');
        }
        $archive->addFromString($entry, '<fixture/>');
        $archive->close();

        return $path;
    }
}
