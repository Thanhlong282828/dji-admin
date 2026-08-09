<?php

namespace Tests\Unit\Integration;

use Illuminate\Validation\ValidationException;
use Modules\Integration\Domain\Support\IntegrationChecksum;
use Modules\Integration\Infrastructure\Adapters\GenericFileAdapter;
use Modules\Integration\Infrastructure\Adapters\GenericHttpAdapter;
use Modules\Integration\Infrastructure\Adapters\IntegrationAdapterRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class IntegrationFoundationTest extends TestCase
{
    public function test_checksum_is_deterministic(): void
    {
        $this->assertSame(IntegrationChecksum::make(['b' => 2, 'a' => 1]), IntegrationChecksum::make(['a' => 1, 'b' => 2]));
    }

    public function test_adapter_routing_and_foundation_delivery(): void
    {
        $registry = new IntegrationAdapterRegistry;
        $this->assertInstanceOf(GenericFileAdapter::class, $registry->for('file_export'));
        $result = $registry->for('file_export')->send([], ['event' => 'ready'], '00000000-0000-4000-8000-000000000001');
        $this->assertTrue($result->delivered);
        $this->assertStringStartsWith('FILE-', $result->externalReference ?? '');
    }

    public function test_http_adapter_rejects_invalid_configuration(): void
    {
        $this->expectException(ValidationException::class);
        (new GenericHttpAdapter)->validateConfiguration(['base_url' => 'not-a-url']);
    }

    #[DataProvider('safeFailureModes')]
    public function test_http_adapter_models_failure_without_external_io(string $mode, string $code): void
    {
        $result = (new GenericHttpAdapter)->send(['base_url' => 'https://example.invalid', 'mode' => $mode], ['safe' => true], 'reliability-test');
        self::assertFalse($result->delivered);
        self::assertSame($code, $result->responseCode);
        self::assertNull($result->externalReference);
    }

    /** @return array<string, array{string,string}> */
    public static function safeFailureModes(): array
    {
        return [
            'timeout' => ['mock_timeout', 'timeout'],
            'http 5xx' => ['mock_5xx', '503'],
            'invalid response' => ['mock_invalid_response', 'invalid_response'],
        ];
    }
}
