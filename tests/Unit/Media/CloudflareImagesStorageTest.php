<?php

namespace Tests\Unit\Media;

use App\Support\Media\CloudflareImagesStorage;
use App\Support\Media\MediaPurpose;
use App\Support\Secrets\ResolvedSecret;
use App\Support\Secrets\SecretProviderInterface;
use App\Support\Secrets\SecretReference;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class CloudflareImagesStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cloudflare_images.account_id', 'account-for-test');
    }

    public function test_upload_does_not_double_close_a_stream_closed_by_the_http_client(): void
    {
        $capturedStream = null;
        $storage = $this->storage(MediaPurpose::ProductCover, function (mixed $stream) use (&$capturedStream): void {
            $capturedStream = $stream;
            fclose($stream);
        });

        $stored = $storage->upload(UploadedFile::fake()->create('cover.png', 1, 'image/png'), MediaPurpose::ProductCover);

        self::assertSame('cloudflare-image-id', $stored->assetId);
        self::assertFalse(is_resource($capturedStream));
    }

    public function test_upload_closes_a_stream_left_open_by_the_http_client(): void
    {
        $capturedStream = null;
        $storage = $this->storage(MediaPurpose::ArticleImage, function (mixed $stream) use (&$capturedStream): void {
            $capturedStream = $stream;
        });

        $stored = $storage->upload(UploadedFile::fake()->create('article.png', 1, 'image/png'), MediaPurpose::ArticleImage);

        self::assertSame('cloudflare-image-id', $stored->assetId);
        self::assertFalse(is_resource($capturedStream));
    }

    public function test_upload_failure_preserves_the_exception_without_exposing_the_token(): void
    {
        $token = 'cloudflare-token-must-not-leak';
        $http = Mockery::mock(Factory::class);
        $pending = Mockery::mock(PendingRequest::class);
        $http->shouldReceive('withToken')->once()->with($token)->andReturn($pending);
        $pending->shouldReceive('timeout')->once()->andReturnSelf();
        $pending->shouldReceive('attach')->once()->andReturnSelf();
        $pending->shouldReceive('post')->once()->andThrow(new RuntimeException('Cloudflare transport unavailable.'));
        $storage = new CloudflareImagesStorage($http, $this->secrets($token));

        try {
            $storage->upload(UploadedFile::fake()->create('avatar.png', 1, 'image/png'), MediaPurpose::EmployeeAvatar);
            self::fail('Expected upload failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Cloudflare transport unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }

    /** @param callable(resource): void $onAttach */
    private function storage(MediaPurpose $purpose, callable $onAttach): CloudflareImagesStorage
    {
        $http = Mockery::mock(Factory::class);
        $pending = Mockery::mock(PendingRequest::class);
        $response = Mockery::mock(Response::class);
        $http->shouldReceive('withToken')->once()->with('cloudflare-test-token')->andReturn($pending);
        $pending->shouldReceive('timeout')->once()->andReturnSelf();
        $pending->shouldReceive('attach')->once()->withArgs(function (string $name, mixed $stream, string $filename) use ($onAttach): bool {
            self::assertSame('file', $name);
            self::assertIsResource($stream);
            self::assertNotSame('', $filename);
            $onAttach($stream);

            return true;
        })->andReturnSelf();
        $pending->shouldReceive('post')->once()->withArgs(function (string $url, array $data) use ($purpose): bool {
            self::assertSame('https://api.cloudflare.com/client/v4/accounts/account-for-test/images/v1', $url);
            self::assertSame(json_encode(['purpose' => $purpose->value], JSON_THROW_ON_ERROR), $data['metadata'] ?? null);

            return true;
        })->andReturn($response);
        $response->shouldReceive('throw')->once()->andReturnSelf();
        $response->shouldReceive('json')->once()->with('result')->andReturn([
            'id' => 'cloudflare-image-id',
            'variants' => ['https://imagedelivery.net/test/cloudflare-image-id/public'],
        ]);

        return new CloudflareImagesStorage($http, $this->secrets('cloudflare-test-token'));
    }

    private function secrets(string $token): SecretProviderInterface
    {
        return new class($token) implements SecretProviderInterface
        {
            public function __construct(private readonly string $token) {}

            public function resolve(SecretReference $reference): ?ResolvedSecret
            {
                return $reference->value === 'secret://media/cloudflare-images'
                    ? new ResolvedSecret($this->token)
                    : null;
            }
        };
    }
}
