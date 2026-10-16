<?php

namespace App\Support\DataQuality;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

final readonly class GceStagingRuntimeIdentityProvider implements StagingRuntimeIdentityProvider
{
    public function __construct(private Factory $http) {}

    /** @return array{project_id:string,instance_id:string,networks:list<string>,subnets:list<string>,public_ips:list<string>} */
    public function identity(): array
    {
        $configuredBaseUrl = config('data-quality.staging_restore.metadata_base_url');
        if (! is_string($configuredBaseUrl) || trim($configuredBaseUrl) === '') {
            throw new RuntimeException('GCE metadata endpoint is not configured.');
        }
        $baseUrl = rtrim(trim($configuredBaseUrl), '/');

        $interfaces = $this->interfaces($baseUrl);

        return [
            'project_id' => $this->required($baseUrl, 'project/project-id'),
            'instance_id' => $this->required($baseUrl, 'instance/id'),
            'networks' => $interfaces['networks'],
            'subnets' => $interfaces['subnets'],
            'public_ips' => $interfaces['public_ips'],
        ];
    }

    private function required(string $baseUrl, string $path): string
    {
        $response = $this->request($baseUrl.'/'.$path);
        if (! $response->successful() || trim($response->body()) === '') {
            throw new RuntimeException('Required GCE metadata is unavailable: '.$path.'.');
        }

        return trim($response->body());
    }

    /** @return array{networks:list<string>,subnets:list<string>,public_ips:list<string>} */
    private function interfaces(string $baseUrl): array
    {
        $response = $this->request($baseUrl.'/instance/network-interfaces/?recursive=true&alt=json');
        if (! $response->successful()) {
            throw new RuntimeException('GCE network-interface metadata is unavailable.');
        }
        $decoded = $response->json();
        if (! is_array($decoded) || $decoded === []) {
            throw new RuntimeException('GCE network-interface metadata is malformed.');
        }
        $networks = [];
        $subnets = [];
        $publicIps = [];
        foreach ($decoded as $interface) {
            if (! is_array($interface) || ! is_string($interface['network'] ?? null) || ! is_string($interface['subnetwork'] ?? null)) {
                throw new RuntimeException('GCE network-interface metadata is malformed.');
            }
            $networks[] = trim($interface['network']);
            $subnets[] = trim($interface['subnetwork']);
            $accessConfigs = $interface['accessConfigs'] ?? [];
            if (! is_array($accessConfigs)) {
                throw new RuntimeException('GCE access-config metadata is malformed.');
            }
            foreach ($accessConfigs as $accessConfig) {
                if (! is_array($accessConfig)) {
                    throw new RuntimeException('GCE access-config metadata is malformed.');
                }
                $externalIp = $accessConfig['externalIp'] ?? null;
                if ($externalIp !== null && (! is_string($externalIp) || trim($externalIp) === '')) {
                    throw new RuntimeException('GCE external-IP metadata is malformed.');
                }
                if (is_string($externalIp)) {
                    $publicIps[] = trim($externalIp);
                }
            }
        }
        $networks = array_values(array_unique($networks));
        $subnets = array_values(array_unique($subnets));
        $publicIps = array_values(array_unique($publicIps));
        sort($networks);
        sort($subnets);
        sort($publicIps);

        return ['networks' => $networks, 'subnets' => $subnets, 'public_ips' => $publicIps];
    }

    private function request(string $url): Response
    {
        try {
            $response = $this->http->withHeaders(['Metadata-Flavor' => 'Google'])
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(1)
                ->timeout(2)
                ->get($url);
        } catch (Throwable $exception) {
            throw new RuntimeException('GCE metadata request failed closed.', previous: $exception);
        }
        if ($response->header('Metadata-Flavor') !== 'Google' || ($response->status() >= 300 && $response->status() < 400)) {
            throw new RuntimeException('GCE metadata response authenticity check failed.');
        }

        return $response;
    }
}
