<?php

namespace Tests\Unit\Operations;

use App\Support\DataQuality\GceStagingRuntimeIdentityProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class GceStagingRuntimeIdentityProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('data-quality.staging_restore.metadata_base_url', 'http://metadata.test/computeMetadata/v1');
    }

    public function test_it_accepts_real_gce_empty_external_ip_and_reads_every_network_interface(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace('http://metadata.test/computeMetadata/v1/', '', $request->url());

            return match ($path) {
                'project/project-id' => $this->metadataResponse('staging-project'),
                'instance/id' => $this->metadataResponse('123456'),
                'instance/network-interfaces/?recursive=true&alt=json' => $this->metadataResponse([
                    ['network' => 'projects/1/global/networks/staging', 'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/a', 'accessConfigs' => [['externalIp' => '', 'type' => 'ONE_TO_ONE_NAT']]],
                    ['network' => 'projects/1/global/networks/private', 'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/b', 'accessConfigs' => []],
                ]),
                default => $this->metadataResponse('', 500),
            };
        });

        $identity = app(GceStagingRuntimeIdentityProvider::class)->identity();

        self::assertSame('staging-project', $identity['project_id']);
        self::assertSame('123456', $identity['instance_id']);
        self::assertCount(2, $identity['networks']);
        self::assertSame([], $identity['public_ips']);
        Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Metadata-Flavor', 'Google'));
        Http::assertSentCount(3);
    }

    public function test_it_rejects_a_public_ip_on_a_second_network_interface(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace('http://metadata.test/computeMetadata/v1/', '', $request->url());

            return $path === 'instance/network-interfaces/?recursive=true&alt=json'
                ? $this->metadataResponse([
                    ['network' => 'projects/1/global/networks/staging', 'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/a', 'accessConfigs' => []],
                    ['network' => 'projects/1/global/networks/other', 'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/b', 'accessConfigs' => [['externalIp' => '203.0.113.10']]],
                ])
                : $this->metadataResponse('valid');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public IP');

        app(GceStagingRuntimeIdentityProvider::class)->identity();
    }

    /** @return iterable<string,array{mixed}> */
    public static function malformedExternalIps(): iterable
    {
        yield 'null' => [null];
        yield 'number' => [1234];
        yield 'array' => [['203.0.113.10']];
        yield 'object' => [(object) ['address' => '203.0.113.10']];
        yield 'invalid non-empty string' => ['not-an-ip'];
    }

    #[DataProvider('malformedExternalIps')]
    public function test_it_rejects_malformed_external_ip_metadata(mixed $externalIp): void
    {
        Http::fake(function (Request $request) use ($externalIp) {
            $path = str_replace('http://metadata.test/computeMetadata/v1/', '', $request->url());

            return $path === 'instance/network-interfaces/?recursive=true&alt=json'
                ? $this->metadataResponse([[
                    'network' => 'projects/1/global/networks/staging',
                    'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/a',
                    'accessConfigs' => [['externalIp' => $externalIp]],
                ]])
                : $this->metadataResponse('valid');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('external-IP metadata is malformed');

        app(GceStagingRuntimeIdentityProvider::class)->identity();
    }

    public function test_it_accepts_metadata_without_access_configs(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace('http://metadata.test/computeMetadata/v1/', '', $request->url());

            return $path === 'instance/network-interfaces/?recursive=true&alt=json'
                ? $this->metadataResponse([[
                    'network' => 'projects/1/global/networks/staging',
                    'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/a',
                ]])
                : $this->metadataResponse('valid');
        });

        $identity = app(GceStagingRuntimeIdentityProvider::class)->identity();

        self::assertSame([], $identity['public_ips']);
    }

    /** @return iterable<string,array{int,array<string,string>}> */
    public static function unauthenticResponses(): iterable
    {
        yield 'missing flavor header' => [200, []];
        yield 'spoofed flavor header' => [200, ['Metadata-Flavor' => 'Not-Google']];
        yield 'redirect rejected' => [302, ['Metadata-Flavor' => 'Google', 'Location' => 'http://attacker.test']];
    }

    /** @param array<string,string> $headers */
    #[DataProvider('unauthenticResponses')]
    public function test_it_fails_closed_on_unauthenticated_or_redirected_metadata(int $status, array $headers): void
    {
        Http::fake(['*' => Http::response('spoofed', $status, $headers)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('authenticity');

        app(GceStagingRuntimeIdentityProvider::class)->identity();
    }

    public function test_it_fails_closed_when_network_metadata_is_malformed(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace('http://metadata.test/computeMetadata/v1/', '', $request->url());

            return $path === 'instance/network-interfaces/?recursive=true&alt=json'
                ? $this->metadataResponse([['network' => 'only-network']])
                : $this->metadataResponse('valid');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');

        app(GceStagingRuntimeIdentityProvider::class)->identity();
    }

    public function test_it_fails_closed_when_network_metadata_is_not_a_list(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace('http://metadata.test/computeMetadata/v1/', '', $request->url());

            return $path === 'instance/network-interfaces/?recursive=true&alt=json'
                ? $this->metadataResponse(['network' => 'unexpected-object'])
                : $this->metadataResponse('valid');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('malformed');

        app(GceStagingRuntimeIdentityProvider::class)->identity();
    }

    public function test_it_fails_closed_when_metadata_transport_times_out(): void
    {
        Http::fake(['*' => Http::failedConnection('simulated timeout')]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('request failed closed');

        app(GceStagingRuntimeIdentityProvider::class)->identity();
    }

    /** @param array<mixed>|string|null $body */
    private function metadataResponse(array|string|null $body, int $status = 200): mixed
    {
        return Http::response($body, $status, ['Metadata-Flavor' => 'Google']);
    }
}
