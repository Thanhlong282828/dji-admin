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

    public function test_it_reads_every_network_interface_and_external_ip_from_authenticated_metadata(): void
    {
        Http::fake(function (Request $request) {
            $path = str_replace('http://metadata.test/computeMetadata/v1/', '', $request->url());

            return match ($path) {
                'project/project-id' => $this->metadataResponse('staging-project'),
                'instance/id' => $this->metadataResponse('123456'),
                'instance/network-interfaces/?recursive=true&alt=json' => $this->metadataResponse([
                    ['network' => 'projects/1/global/networks/staging', 'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/a', 'accessConfigs' => []],
                    ['network' => 'projects/1/global/networks/private', 'subnetwork' => 'projects/1/regions/asia-southeast1/subnetworks/b', 'accessConfigs' => [['externalIp' => '203.0.113.10']]],
                ]),
                default => $this->metadataResponse('', 500),
            };
        });

        $identity = app(GceStagingRuntimeIdentityProvider::class)->identity();

        self::assertSame('staging-project', $identity['project_id']);
        self::assertSame('123456', $identity['instance_id']);
        self::assertCount(2, $identity['networks']);
        self::assertSame(['203.0.113.10'], $identity['public_ips']);
        Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Metadata-Flavor', 'Google'));
        Http::assertSentCount(3);
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

    public function test_it_fails_closed_when_metadata_transport_times_out(): void
    {
        Http::fake(['*' => Http::failedConnection('simulated timeout')]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('request failed closed');

        app(GceStagingRuntimeIdentityProvider::class)->identity();
    }

    private function metadataResponse(string|array $body, int $status = 200): mixed
    {
        return Http::response($body, $status, ['Metadata-Flavor' => 'Google']);
    }
}
