<?php

declare(strict_types=1);

namespace App\Services;

use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\SecretManager\V1\AccessSecretVersionRequest;
use RuntimeException;

final class GcpSecretManagerService
{
    private SecretManagerServiceClient $client;

    private string $projectId;

    private string $defaultVersion;

    public function __construct()
    {
        $this->projectId = $this->stringConfig(
            'secret-manager.project_id'
        );

        $this->defaultVersion = $this->stringConfig(
            'secret-manager.default_version',
            'latest'
        );

        if ($this->projectId === '') {
            throw new RuntimeException(
                'GCP_PROJECT_ID is not configured.'
            );
        }

        if ($this->defaultVersion === '') {
            throw new RuntimeException(
                'GCP_SECRET_MANAGER_DEFAULT_VERSION is not configured.'
            );
        }

        /*
         * No credentials file is supplied here.
         *
         * In production on Compute Engine, the Google Cloud client
         * library uses Application Default Credentials (ADC).
         */
        $this->client = new SecretManagerServiceClient();
    }

    public function get(
        string $secretId,
        ?string $version = null
    ): string {
        $secretId = trim($secretId);
        $resolvedVersion = trim(
            $version ?? $this->defaultVersion
        );

        if ($secretId === '') {
            throw new RuntimeException(
                'Secret ID must not be empty.'
            );
        }

        if ($resolvedVersion === '') {
            throw new RuntimeException(
                'Secret version must not be empty.'
            );
        }

        $name = $this->client->secretVersionName(
            $this->projectId,
            $secretId,
            $resolvedVersion
        );

        $response = $this->client->accessSecretVersion(
            (new AccessSecretVersionRequest())
                ->setName($name)
        );

        $payload = $response->getPayload();

        if ($payload === null) {
            throw new RuntimeException(
                'Secret Manager returned no payload.'
            );
        }

        $value = $payload->getData();

        if ($value === '') {
            throw new RuntimeException(
                'Secret Manager returned an empty secret.'
            );
        }

        return $value;
    }

    public function close(): void
    {
        $this->client->close();
    }

    private function stringConfig(
        string $key,
        string $default = ''
    ): string {
        $value = config($key, $default);

        return is_string($value)
            ? trim($value)
            : $default;
    }
}
