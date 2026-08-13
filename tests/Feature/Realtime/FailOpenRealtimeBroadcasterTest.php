<?php

namespace Tests\Feature\Realtime;

use App\Support\Broadcasting\FailOpenRealtimeBroadcaster;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Company\Domain\Models\Company;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Tests\TestCase;

final class FailOpenRealtimeBroadcasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_transport_failure_does_not_replace_committed_business_mutation(): void
    {
        $this->configureRealtime(true);
        $logger = new InMemoryRealtimeLogger;
        $broadcaster = $this->broadcaster(new ThrowingRealtimeTransport(new BroadcastException('Reverb unavailable.')), $logger);
        $company = Company::query()->create([
            'code' => 'REALTIME-SAFE',
            'name' => 'Realtime Safe',
            'country_code' => 'VN',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency_code' => 'VND',
            'currency' => 'VND',
            'status' => 'active',
            'is_active' => true,
        ]);

        $company->update(['name' => 'Realtime Safe Updated']);
        $broadcaster->broadcast(['private-company.1'], 'company.updated', ['restricted' => 'must-not-be-logged']);

        self::assertSame('Realtime Safe Updated', $company->fresh()?->name);
        self::assertCount(1, $logger->records);
        self::assertSame('realtime_broadcast', $logger->records[0]['context']['component'] ?? null);
        self::assertArrayNotHasKey('payload', $logger->records[0]['context']);
        self::assertStringNotContainsString('restricted', json_encode($logger->records, JSON_THROW_ON_ERROR));
    }

    public function test_non_broadcast_exception_still_propagates(): void
    {
        $this->configureRealtime(true);
        $broadcaster = $this->broadcaster(new ThrowingRealtimeTransport(new RuntimeException('Programming failure.')), new InMemoryRealtimeLogger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Programming failure.');

        $broadcaster->broadcast([], 'test.event');
    }

    public function test_disabled_realtime_flag_skips_transport_delivery(): void
    {
        $this->configureRealtime(false);
        $transport = new CountingRealtimeTransport;
        $broadcaster = $this->broadcaster($transport, new InMemoryRealtimeLogger);

        $broadcaster->broadcast([], 'test.event');

        self::assertSame(0, $transport->broadcasts);
    }

    private function broadcaster(Broadcaster $transport, InMemoryRealtimeLogger $logger): FailOpenRealtimeBroadcaster
    {
        return new FailOpenRealtimeBroadcaster(
            $transport,
            new RuntimeConfigurationService(new Repository(new ArrayStore)),
            $logger,
        );
    }

    private function configureRealtime(bool $enabled): void
    {
        $flags = config('runtime-configuration.flags', []);
        self::assertIsArray($flags);
        $flags['realtime.reverb'] = [
            'enabled' => $enabled,
            'owner' => 'realtime',
            'risk' => 'medium',
            'failure' => 'open',
            'remove_when' => 'realtime becomes a critical dependency',
        ];
        config()->set('runtime-configuration.flags', $flags);
    }
}

final class ThrowingRealtimeTransport implements Broadcaster
{
    public function __construct(private readonly RuntimeException $exception) {}

    public function auth($request): mixed
    {
        return null;
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    /** @param array<int, mixed> $channels
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        throw $this->exception;
    }
}

final class CountingRealtimeTransport implements Broadcaster
{
    public int $broadcasts = 0;

    public function auth($request): mixed
    {
        return null;
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    /** @param array<int, mixed> $channels
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $this->broadcasts++;
    }
}

final class InMemoryRealtimeLogger extends AbstractLogger
{
    /** @var list<array{level:mixed,message:string,context:array<string,mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
