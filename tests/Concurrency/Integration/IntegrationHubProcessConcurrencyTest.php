<?php

namespace Tests\Concurrency\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Integration\Domain\Support\IntegrationChecksum;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('integration-mysql-concurrency')]
final class IntegrationHubProcessConcurrencyTest extends TestCase
{
    public function test_all_five_process_races_preserve_integration_invariants(): void
    {
        Artisan::call('integration:browser-fixture');
        try {
            $company = $this->id('companies', 'code', 'INTEGRATION-FIXTURE');
            $connection = $this->id('integration_connections', 'company_id', $company);
            $actor = $this->id('users', 'email', 'integration.browser@schema.test');
            $runner = new MysqlProcessRaceRunner;
            $key = (string) Str::uuid();
            $base = ['company_id' => $company, 'connection_id' => $connection, 'actor_id' => $actor, 'token' => 'queue-race', 'idempotency_key' => $key];
            $queue = $runner->run('integration_queue', $base, $base);
            self::assertSame([0, 0], $queue['exit_codes'], implode("\n", $queue['outputs']));
            self::assertSame(1, DB::table('integration_outbound_messages')->where(['company_id' => $company, 'idempotency_key' => $key])->count());

            $message = $this->id('integration_outbound_messages', 'idempotency_key', $key);
            $delivery = $runner->run('integration_delivery', ['company_id' => $company, 'message_id' => $message, 'actor_id' => $actor], ['company_id' => $company, 'message_id' => $message, 'actor_id' => $actor]);
            self::assertSame([0, 0], $delivery['exit_codes'], implode("\n", $delivery['outputs']));
            self::assertSame(1, DB::table('integration_delivery_attempts')->where('outbound_message_id', $message)->count());
            self::assertSame(1, DB::table('integration_external_references')->where('outbound_message_id', $message)->count());

            $failed = $this->failedMessage($company, $connection, $actor);
            $retry = $runner->run('integration_retry', ['company_id' => $company, 'message_id' => $failed, 'actor_id' => $actor], ['company_id' => $company, 'message_id' => $failed, 'actor_id' => $actor]);
            self::assertSame([0, 0], $retry['exit_codes'], implode("\n", $retry['outputs']));
            self::assertSame('delivered', DB::table('integration_outbound_messages')->where('id', $failed)->value('status'));
            self::assertSame(1, DB::table('integration_delivery_attempts')->where('outbound_message_id', $failed)->count());

            $payload = ['token' => 'webhook-race'];
            $secret = 'fixture-webhook-secret';
            DB::table('integration_connections')->where('id', $connection)->update(['configuration' => json_encode(['webhook_secret' => $secret], JSON_THROW_ON_ERROR)]);
            $signature = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);
            $webhook = ['company_id' => $company, 'connection_id' => $connection, 'actor_id' => $actor, 'event_id' => 'event-race', 'token' => 'webhook-race', 'signature' => $signature];
            $race = $runner->run('integration_webhook', $webhook, $webhook);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('integration_webhook_receipts')->where(['company_id' => $company, 'event_id' => 'event-race'])->count());
            self::assertSame(1, DB::table('integration_inbound_messages')->where(['company_id' => $company, 'event_id' => 'event-race'])->count());

            $dead = $this->id('integration_dead_letters', 'company_id', $company);
            $replayKey = (string) Str::uuid();
            $replay = ['company_id' => $company, 'dead_letter_id' => $dead, 'actor_id' => $actor, 'idempotency_key' => $replayKey];
            $race = $runner->run('integration_replay', $replay, $replay);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('integration_dead_letters')->where('id', $dead)->value('replay_count'));
        } finally {
            Artisan::call('integration:browser-fixture', ['--cleanup' => true]);
        }
    }

    private function failedMessage(int $company, int $connection, int $actor): int
    {
        return (int) DB::table('integration_outbound_messages')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'connection_id' => $connection, 'source_type' => 'race', 'message_type' => 'race.retry', 'status' => 'failed', 'payload' => '{}', 'payload_checksum' => IntegrationChecksum::make([]), 'idempotency_key' => (string) Str::uuid(), 'attempt_count' => 0, 'available_at' => now(), 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function id(string $table, string $column, int|string $value): int
    {
        $id = DB::table($table)->where($column, $value)->value('id');

        return is_numeric($id) ? (int) $id : self::fail("Missing fixture {$table}");
    }
}
