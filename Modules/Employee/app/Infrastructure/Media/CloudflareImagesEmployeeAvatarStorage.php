<?php

namespace Modules\Employee\Infrastructure\Media;

use App\Support\Secrets\SecretProviderInterface;
use App\Support\Secrets\SecretReference;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Modules\Employee\Domain\Services\EmployeeAvatarStorageInterface;
use Modules\Employee\Domain\Services\StoredEmployeeAvatar;
use RuntimeException;

final readonly class CloudflareImagesEmployeeAvatarStorage implements EmployeeAvatarStorageInterface
{
    public function __construct(private Factory $http, private SecretProviderInterface $secrets) {}

    public function upload(UploadedFile $file): StoredEmployeeAvatar
    {
        [$accountId, $token] = $this->credentials();
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Không thể đọc ảnh đại diện tạm thời.');
        }
        try {
            $response = $this->http->withToken($token)->attach('file', $stream, $file->getClientOriginalName())
                ->post('https://api.cloudflare.com/client/v4/accounts/'.rawurlencode($accountId).'/images/v1')
                ->throw()
                ->json('result');
        } finally {
            fclose($stream);
        }
        if (! is_array($response) || ! is_string($response['id'] ?? null) || $response['id'] === '') {
            throw new RuntimeException('Cloudflare Images không trả về image ID hợp lệ.');
        }
        $variants = $response['variants'] ?? [];
        $url = is_array($variants) && is_string($variants[0] ?? null) ? $variants[0] : $this->deliveryUrl($response['id']);

        return new StoredEmployeeAvatar($response['id'], $url);
    }

    public function delete(string $reference): void
    {
        [$accountId, $token] = $this->credentials();
        $this->http->withToken($token)
            ->delete('https://api.cloudflare.com/client/v4/accounts/'.$accountId.'/images/v1/'.rawurlencode($reference))
            ->throw();
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

        return is_string($hash) && $hash !== ''
            ? 'https://imagedelivery.net/'.rawurlencode($hash).'/'.rawurlencode($imageId).'/public'
            : null;
    }
}
