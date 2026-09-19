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
        $projectId = (string) config('cloud-storage.project_id');
        $bucketName = (string) config('cloud-storage.bucket');
        $this->serviceAccountEmail = (string) config(
            'cloud-storage.service_account_email'
        );
        $this->signedUrlTtlMinutes = (int) config(
            'cloud-storage.signed_url_ttl_minutes',
            5
        );

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
}
