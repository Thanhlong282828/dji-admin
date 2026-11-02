<?php

namespace App\Support\Monitoring;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

final readonly class GceMonitoringMetadataProvider
{
    private const BASE_URL = 'http://metadata.google.internal/computeMetadata/v1';

    public function __construct(private Factory $http) {}

    /** @return array{project_id:string,instance_id:string,zone:string,access_token:string} */
    public function credentials(): array
    {
        $zonePath = $this->required('instance/zone');
        $zone = basename($zonePath);
        if (preg_match('/^[a-z]+-[a-z]+\d-[a-z]$/', $zone) !== 1) {
            throw new RuntimeException('GCE metadata returned an invalid instance zone.');
        }

        $tokenResponse = $this->request('instance/service-accounts/default/token');
        $token = $tokenResponse->json();
        $accessToken = is_array($token) ? ($token['access_token'] ?? null) : null;
        if (! is_string($accessToken) || trim($accessToken) === '') {
            throw new RuntimeException('GCE metadata did not return a monitoring access token.');
        }

        return [
            'project_id' => $this->required('project/project-id'),
            'instance_id' => $this->required('instance/id'),
            'zone' => $zone,
            'access_token' => trim($accessToken),
        ];
    }

    private function required(string $path): string
    {
        $response = $this->request($path);
        $value = trim($response->body());
        if ($value === '') {
            throw new RuntimeException('Required GCE monitoring metadata is unavailable.');
        }

        return $value;
    }

    private function request(string $path): Response
    {
        try {
            $response = $this->http->withHeaders(['Metadata-Flavor' => 'Google'])
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(1)
                ->timeout(2)
                ->get(self::BASE_URL.'/'.ltrim($path, '/'));
        } catch (Throwable $exception) {
            throw new RuntimeException('GCE monitoring metadata request failed closed.', previous: $exception);
        }

        if (! $response->successful() || $response->header('Metadata-Flavor') !== 'Google') {
            throw new RuntimeException('GCE monitoring metadata response failed authenticity validation.');
        }

        return $response;
    }
}
