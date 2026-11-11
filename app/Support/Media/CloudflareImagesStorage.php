<?php

namespace App\Support\Media;

use App\Support\Secrets\SecretProviderInterface;
use App\Support\Secrets\SecretReference;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final readonly class CloudflareImagesStorage implements MediaStorageInterface
{
    public function __construct(private Factory $http, private SecretProviderInterface $secrets) {}

    public function upload(UploadedFile $file, MediaPurpose $purpose): StoredMedia
    {
        [$accountId, $token] = $this->credentials();
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Không thể đọc media tạm thời.');
        }
        try {
            $result = $this->http->withToken($token)->timeout($this->timeout())
                ->attach('file', $stream, $file->getClientOriginalName())
                ->post('https://api.cloudflare.com/client/v4/accounts/'.rawurlencode($accountId).'/images/v1', ['metadata' => json_encode(['purpose' => $purpose->value], JSON_THROW_ON_ERROR)])
                ->throw()->json('result');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! is_array($result) || ! is_string($result['id'] ?? null) || $result['id'] === '') {
            throw new RuntimeException('Cloudflare Images không trả về image ID hợp lệ.');
        }
        $variants = $result['variants'] ?? [];
        $url = is_array($variants) && is_string($variants[0] ?? null) ? $variants[0] : $this->deliveryUrl($result['id']);

        return new StoredMedia('cloudflare-images', $result['id'], $url, (string) $file->getMimeType(), (int) $file->getSize(), MediaFileFingerprint::sha256($file));
    }

    public function delete(string $assetId): void
    {
        [$accountId, $token] = $this->credentials();
        $this->http->withToken($token)->timeout($this->timeout())
            ->delete('https://api.cloudflare.com/client/v4/accounts/'.rawurlencode($accountId).'/images/v1/'.rawurlencode($assetId))->throw();
    }

    /** @return array{string,string} */
    private function credentials(): array
    {
        $accountId = config('services.cloudflare_images.account_id');
        $secret = $this->secrets->resolve(new SecretReference('secret://media/cloudflare-images'));
        if (! is_string($accountId) || $accountId === '' || $secret === null) {
            throw new RuntimeException('Cloudflare Images chưa được cấu hình.');
        }

        return [$accountId, $secret->revealAtInfrastructureBoundary()];
    }

    private function deliveryUrl(string $imageId): ?string
    {
        $hash = config('services.cloudflare_images.delivery_hash');

        return is_string($hash) && $hash !== '' ? 'https://imagedelivery.net/'.rawurlencode($hash).'/'.rawurlencode($imageId).'/public' : null;
    }

    private function timeout(): int
    {
        $configured = config('media.timeout', 15);

        return is_int($configured) && $configured > 0 ? $configured : 15;
    }
}
