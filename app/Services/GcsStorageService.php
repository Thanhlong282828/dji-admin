<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use RuntimeException;

final class GcsStorageService
{
    private StorageClient $client;

    private Bucket $bucket;

    private string $serviceAccountEmail;

    private int $signedUrlTtlMinutes;

    public function __construct()
    {
        $projectId = $this->stringConfig('cloud-storage.project_id');
        $bucketName = $this->stringConfig('cloud-storage.bucket');
        $this->serviceAccountEmail = $this->stringConfig('cloud-storage.service_account_email');
        $this->signedUrlTtlMinutes = $this->integerConfig('cloud-storage.signed_url_ttl_minutes', 5);

        if ($projectId === '') {
            throw new RuntimeException(
                'GCP_PROJECT_ID is not configured.'
            );
        }

        if ($bucketName === '') {
            throw new RuntimeException(
                'GCS_BUCKET is not configured.'
            );
        }

        if ($this->signedUrlTtlMinutes < 1) {
            throw new RuntimeException(
                'GCS_SIGNED_URL_TTL_MINUTES must be at least 1.'
            );
        }

        $this->client = new StorageClient([
            'projectId' => $projectId,
        ]);

        $this->bucket = $this->client->bucket($bucketName);
    }

    /** @param array<string, mixed> $metadata */
    public function put(
        string $path,
        string $contents,
        array $metadata = []
    ): void {
        $this->bucket->upload(
            $contents,
            [
                'name' => $path,
                'metadata' => $metadata,
            ]
        );
    }

    public function get(string $path): string
    {
        return $this->bucket
            ->object($path)
            ->downloadAsString();
    }

    public function exists(string $path): bool
    {
        return $this->bucket
            ->object($path)
            ->exists();
    }

    public function delete(string $path): void
    {
        $object = $this->bucket->object($path);

        if ($object->exists()) {
            $object->delete();
        }
    }

    public function temporaryUrl(
        string $path,
        ?int $ttlMinutes = null
    ): string {
        $ttl = $ttlMinutes ?? $this->signedUrlTtlMinutes;

        if ($ttl < 1) {
            throw new RuntimeException(
                'Signed URL TTL must be at least 1 minute.'
            );
        }

        if ($this->serviceAccountEmail === '') {
            throw new RuntimeException(
                'GCS_SERVICE_ACCOUNT_EMAIL is not configured.'
            );
        }

        return $this->bucket
            ->object($path)
            ->signedUrl(
                new DateTimeImmutable("+{$ttl} minutes"),
                [
                    'version' => 'v4',
                    'method' => 'GET',
                    'serviceAccountEmail' => $this->serviceAccountEmail,
                ]
            );
    }

    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    private function integerConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : $default;
    }
}
