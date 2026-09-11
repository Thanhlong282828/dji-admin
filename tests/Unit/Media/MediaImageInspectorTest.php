<?php

namespace Tests\Unit\Media;

use App\Support\Media\LocalMediaStorage;
use App\Support\Media\MediaPurpose;
use App\Support\Media\MediaService;
use App\Support\Media\MediaStorageInterface;
use App\Support\Media\StoredMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Services\EmployeeAvatarService;
use Modules\Employee\Domain\Services\EmployeeAvatarStorageInterface;
use Modules\Employee\Domain\Services\StoredEmployeeAvatar;
use Tests\TestCase;

final class MediaImageInspectorTest extends TestCase
{
    /** @var list<string> */
    private array $fixturePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->fixturePaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_product_media_accepts_rgba_rgb_palette_jpeg_webp_and_both_4k_orientations_without_reencoding(): void
    {
        $storage = new RecordingMediaStorage;
        $service = new MediaService($storage, app('db'));
        $files = [
            $this->png(900, 900, false, false, 'rgb-over-2mb.png'),
            $this->png(900, 900, true, false, 'rgba-over-2mb.png'),
            $this->png(320, 240, true, true, 'palette.png'),
            UploadedFile::fake()->image('photo.jpg', 640, 480),
            UploadedFile::fake()->image('landscape.jpg', 3840, 2160),
            UploadedFile::fake()->image('portrait.jpg', 2160, 3840),
        ];
        if (function_exists('imagewebp')) {
            $files[] = $this->webp(640, 480);
        }

        foreach (array_slice($files, 0, 2) as $file) {
            self::assertGreaterThan(2 * 1024 * 1024, $file->getSize());
            self::assertLessThan(5 * 1024 * 1024, $file->getSize());
        }

        $storedMedia = [];
        foreach ($files as $file) {
            $checksum = hash_file('sha256', $file->getRealPath());
            $stored = $service->stage($file, MediaPurpose::ProductCover);
            self::assertSame($checksum, $stored->checksum);
            $storedMedia[] = $stored;
        }

        self::assertCount(count($files), $storage->uploads);
        self::assertSame(64, $this->alphaAt($storage->uploads[1]));
        foreach ($storedMedia as $stored) {
            $service->compensate($stored);
        }
        self::assertSame([], array_values(array_filter($storage->uploads, 'file_exists')));
    }

    public function test_two_megabyte_png_reaches_every_shared_media_purpose_and_is_cleaned_up(): void
    {
        $storage = new RecordingMediaStorage;
        $service = new MediaService($storage, app('db'));
        $file = $this->png(900, 900, false, false, 'shared-ingress.png');
        self::assertGreaterThan(2 * 1024 * 1024, $file->getSize());

        foreach (MediaPurpose::cases() as $purpose) {
            $stored = $service->stage($file, $purpose);
            $service->compensate($stored);
        }

        self::assertCount(count(MediaPurpose::cases()), $storage->uploads);
        self::assertSame([], array_values(array_filter($storage->uploads, 'file_exists')));
    }

    public function test_local_storage_ignores_hostile_original_filename_and_cleanup_is_exact(): void
    {
        Storage::fake('public');
        $service = new MediaService(new LocalMediaStorage, app('db'));
        $file = $this->png(64, 64, false, false, '../../avatar.php.png');

        $stored = $service->stage($file, MediaPurpose::ProductCover);

        self::assertMatchesRegularExpression('#^media/product_cover/[0-9a-f-]+\\.png$#', $stored->assetId);
        self::assertStringNotContainsString('..', $stored->assetId);
        Storage::disk('public')->assertExists($stored->assetId);
        $service->compensate($stored);
        Storage::disk('public')->assertMissing($stored->assetId);
    }

    public function test_employee_avatar_accepts_valid_png_above_two_megabytes_without_cloudflare(): void
    {
        $storage = new class implements EmployeeAvatarStorageInterface
        {
            public int $uploads = 0;

            public function upload(UploadedFile $file): StoredEmployeeAvatar
            {
                $this->uploads++;

                return new StoredEmployeeAvatar('local-fixture', null);
            }

            public function delete(string $reference): void {}
        };
        $file = $this->png(900, 900, true, false, 'avatar-rgba-over-2mb.png');
        self::assertGreaterThan(2 * 1024 * 1024, $file->getSize());
        self::assertLessThan(5 * 1024 * 1024, $file->getSize());

        $stored = (new EmployeeAvatarService($storage))->stage($file);

        self::assertSame('local-fixture', $stored->reference);
        self::assertSame(1, $storage->uploads);
    }

    public function test_local_web_ingress_is_above_two_megabytes_without_weakening_application_limit(): void
    {
        $composer = file_get_contents(base_path('composer.json'));
        self::assertIsString($composer);
        self::assertStringContainsString('php -d upload_max_filesize=12M -d post_max_size=14M -S 127.0.0.1:8000', $composer);

        $userIni = parse_ini_file(public_path('.user.ini'));
        self::assertIsArray($userIni);
        self::assertSame('12M', $userIni['upload_max_filesize']);
        self::assertSame('14M', $userIni['post_max_size']);

        $storage = new RecordingMediaStorage;
        $service = new MediaService($storage, app('db'));
        try {
            $service->stage($this->oversizedPng(), MediaPurpose::ProductCover);
            self::fail('The 10 MiB application limit must remain authoritative.');
        } catch (ValidationException) {
            self::assertSame([], $storage->uploads);
        }
    }

    public function test_product_media_rejects_oversized_fake_corrupt_and_unsupported_files_before_storage(): void
    {
        $storage = new RecordingMediaStorage;
        $service = new MediaService($storage, app('db'));
        $cases = [
            $this->oversizedPng(),
            UploadedFile::fake()->createWithContent('fake.png', 'not an image'),
            UploadedFile::fake()->createWithContent('corrupt.png', "\x89PNG\r\n\x1a\ntruncated"),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('unsupported.gif', 32, 32),
            UploadedFile::fake()->image('too-wide.png', 6001, 1),
        ];

        foreach ($cases as $file) {
            try {
                $service->stage($file, MediaPurpose::ProductImage);
                self::fail('Invalid media must be rejected: '.$file->getClientOriginalName());
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('media', $exception->errors());
            }
        }

        self::assertSame([], $storage->uploads);
    }

    private function png(int $width, int $height, bool $alpha, bool $palette, string $name): UploadedFile
    {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('Image dimensions must be positive.');
        }
        $path = tempnam(sys_get_temp_dir(), 'media-png-');
        self::assertIsString($path);
        $this->fixturePaths[] = $path;
        $image = $palette ? imagecreate($width, $height) : imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagealphablending($image, ! $alpha);
        imagesavealpha($image, $alpha);
        $color = imagecolorallocatealpha($image, 24, 120, 210, $alpha ? 64 : 0);
        self::assertIsInt($color);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path, 0);
        imagedestroy($image);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function webp(int $width, int $height): UploadedFile
    {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('Image dimensions must be positive.');
        }
        $path = tempnam(sys_get_temp_dir(), 'media-webp-');
        self::assertIsString($path);
        $this->fixturePaths[] = $path;
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        $color = imagecolorallocate($image, 90, 80, 70);
        self::assertIsInt($color);
        imagefill($image, 0, 0, $color);
        imagewebp($image, $path, 90);
        imagedestroy($image);

        return new UploadedFile($path, 'image.webp', 'image/webp', null, true);
    }

    private function oversizedPng(): UploadedFile
    {
        $file = $this->png(32, 32, true, false, 'oversized.png');
        file_put_contents($file->getRealPath(), str_repeat('x', (10 * 1024 * 1024) + 1), FILE_APPEND);
        clearstatcache(true, $file->getRealPath());

        return new UploadedFile($file->getRealPath(), 'oversized.png', 'image/png', null, true);
    }

    private function alphaAt(string $path): int
    {
        $image = imagecreatefrompng($path);
        self::assertNotFalse($image);
        $index = imagecolorat($image, 0, 0);
        self::assertIsInt($index);
        $color = imagecolorsforindex($image, $index);
        imagedestroy($image);

        return $color['alpha'];
    }
}

final class RecordingMediaStorage implements MediaStorageInterface
{
    /** @var list<string> */
    public array $uploads = [];

    public function upload(UploadedFile $file, MediaPurpose $purpose): StoredMedia
    {
        $path = tempnam(sys_get_temp_dir(), 'recorded-media-');
        if (! is_string($path) || ! copy($file->getRealPath(), $path)) {
            throw new \RuntimeException('Could not record uploaded media.');
        }
        $this->uploads[] = $path;

        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum)) {
            throw new \RuntimeException('Could not hash recorded media.');
        }

        return new StoredMedia('test', $path, null, (string) $file->getMimeType(), (int) $file->getSize(), $checksum);
    }

    public function delete(string $assetId): void
    {
        if (is_file($assetId)) {
            unlink($assetId);
        }
    }
}
