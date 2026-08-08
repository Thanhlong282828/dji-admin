<?php

namespace Modules\Integration\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Integration\Domain\Contracts\IntegrationAdapterRegistryInterface;
use Modules\Integration\Domain\Contracts\IntegrationCredentialProviderInterface;
use Modules\Integration\Domain\Support\IntegrationChecksum;

final readonly class IntegrationHubService
{
    public function __construct(private ConnectionInterface $db, private IntegrationAdapterRegistryInterface $adapters, private IntegrationCredentialProviderInterface $credentials) {}

    /** @param array<string,mixed> $data */
    public function createConnection(int $companyId, int $actorId, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actorId, $data): int {
            $type = $this->requiredString($data, 'type');
            $config = ['base_url' => $data['base_url'] ?? null, 'mode' => $data['mode'] ?? 'foundation'];
            $this->adapters->for($type)->validateConfiguration($config);
            $payload = ['code' => $this->requiredString($data, 'code'), 'name' => $this->requiredString($data, 'name'), 'type' => $type, 'configuration' => $config];
            $credential = $data['credential_reference'] ?? null;
            $id = (int) $this->db->table('integration_connections')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'code' => $payload['code'], 'name' => $payload['name'], 'type' => $type, 'base_url' => $data['base_url'] ?? null, 'configuration' => json_encode($config, JSON_THROW_ON_ERROR), 'credential_reference' => is_string($credential) && $credential !== '' ? $this->credentials->protect($credential) : null, 'checksum' => IntegrationChecksum::make($payload), 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $this->history($companyId, 'connection', $id, 'created', null, 'draft', $actorId);

            return $id;
        }, 3);
    }

    public function activate(int $companyId, int $connectionId, int $actorId): void
    {
        $this->db->transaction(function () use ($companyId, $connectionId, $actorId): void {
            $row = $this->connection($companyId, $connectionId, true);
            $from = $this->s($row, 'status');
            if ($from === 'active') {
                return;
            }if (! in_array($from, ['draft', 'inactive'], true)) {
                throw ValidationException::withMessages(['status' => 'Invalid connection transition.']);
            }$this->db->table('integration_connections')->where('id', $connectionId)->update(['status' => 'active', 'version' => $this->i($row, 'version') + 1, 'updated_by' => $actorId, 'updated_at' => now()]);
            $this->history($companyId, 'connection', $connectionId, 'activated', $from, 'active', $actorId);
        }, 3);
    }

    /** @param array<string,mixed> $payload */
    public function queue(int $companyId, int $connectionId, int $actorId, string $sourceType, ?int $sourceId, string $messageType, array $payload, string $key): int
    {
        return $this->db->transaction(function () use ($companyId, $connectionId, $actorId, $sourceType, $sourceId, $messageType, $payload, $key): int {
            $existing = $this->db->table('integration_outbound_messages')->where(['company_id' => $companyId, 'idempotency_key' => $key])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }$connection = $this->connection($companyId, $connectionId, true);
            if ($this->s($connection, 'status') !== 'active') {
                throw ValidationException::withMessages(['connection' => 'Connection must be active.']);
            }$id = (int) $this->db->table('integration_outbound_messages')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'connection_id' => $connectionId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'message_type' => $messageType, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => IntegrationChecksum::make($payload), 'idempotency_key' => $key, 'available_at' => now(), 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $this->history($companyId, 'outbound_message', $id, 'queued', null, 'queued', $actorId);

            return $id;
        }, 5);
    }

    public function deliver(int $companyId, int $messageId): void
    {
        $this->db->transaction(function () use ($companyId, $messageId): void {
            $m = $this->message($companyId, $messageId, true);
            if ($this->s($m, 'status') === 'delivered') {
                return;
            }if (! in_array($this->s($m, 'status'), ['queued', 'failed'], true)) {
                throw ValidationException::withMessages(['status' => 'Message cannot be delivered.']);
            }$c = $this->connection($companyId, $this->i($m, 'connection_id'), true);
            $attempt = $this->i($m, 'attempt_count') + 1;
            $payload = $this->json($m, 'payload');
            $config = $this->json($c, 'configuration');
            $result = $this->adapters->for($this->s($c, 'type'))->send($config, $payload, $this->s($m, 'idempotency_key'));
            $status = $result->delivered ? 'delivered' : 'failed';
            $this->db->table('integration_delivery_attempts')->insertOrIgnore(['company_id' => $companyId, 'outbound_message_id' => $messageId, 'attempt_number' => $attempt, 'status' => $status, 'external_reference' => $result->externalReference, 'response_code' => $result->responseCode, 'response_checksum' => hash('sha256', $result->safeMessage), 'safe_message' => $result->safeMessage, 'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->db->table('integration_outbound_messages')->where('id', $messageId)->update(['status' => $status, 'attempt_count' => $attempt, 'delivered_at' => $result->delivered ? now() : null, 'failure_code' => $result->delivered ? null : $result->responseCode, 'safe_failure_message' => $result->delivered ? null : $result->safeMessage, 'updated_at' => now()]);
            if ($result->delivered && $result->externalReference !== null) {
                $this->db->table('integration_external_references')->insertOrIgnore(['company_id' => $companyId, 'outbound_message_id' => $messageId, 'provider' => $this->s($c, 'type'), 'external_reference' => $result->externalReference, 'checksum' => hash('sha256', $result->externalReference), 'created_at' => now(), 'updated_at' => now()]);
            }if (! $result->delivered && $attempt >= $this->i($c, 'max_attempts')) {
                $this->deadLetter($companyId, $messageId, $result->responseCode, $result->safeMessage);
            }$this->history($companyId, 'outbound_message', $messageId, $status, $this->s($m, 'status'), $status, null);
        }, 5);
    }

    public function retry(int $companyId, int $messageId, int $actorId): void
    {
        $this->db->transaction(function () use ($companyId, $messageId, $actorId): void {
            $m = $this->message($companyId, $messageId, true);
            if ($this->s($m, 'status') === 'delivered') {
                return;
            }
            if ($this->s($m, 'status') === 'queued' && $this->db->table('integration_histories')->where(['company_id' => $companyId, 'aggregate_type' => 'outbound_message', 'aggregate_id' => $messageId, 'action' => 'retried'])->exists()) {
                return;
            }
            if (! in_array($this->s($m, 'status'), ['failed', 'dead_letter'], true)) {
                throw ValidationException::withMessages(['status' => 'Only failed messages can be retried.']);
            }$this->db->table('integration_outbound_messages')->where('id', $messageId)->update(['status' => 'queued', 'available_at' => now(), 'updated_at' => now()]);
            $this->history($companyId, 'outbound_message', $messageId, 'retried', $this->s($m, 'status'), 'queued', $actorId);
        }, 3);
        $this->deliver($companyId, $messageId);
    }

    public function replay(int $companyId, int $deadLetterId, int $actorId, string $key): void
    {
        $this->db->transaction(function () use ($companyId, $deadLetterId, $actorId, $key): void {
            $d = $this->db->table('integration_dead_letters')->where(['company_id' => $companyId, 'id' => $deadLetterId])->lockForUpdate()->first();
            abort_unless(is_object($d), 404);
            if ($this->s($d, 'status') === 'resolved' || $this->s($d, 'last_replay_key') === $key) {
                return;
            }$this->db->table('integration_dead_letters')->where('id', $deadLetterId)->update(['status' => 'replaying', 'replay_count' => $this->i($d, 'replay_count') + 1, 'last_replay_key' => $key, 'updated_at' => now()]);
            $this->db->table('integration_outbound_messages')->where('id', $this->i($d, 'outbound_message_id'))->update(['status' => 'queued', 'available_at' => now(), 'updated_at' => now()]);
            $this->history($companyId, 'dead_letter', $deadLetterId, 'replayed', 'open', 'replaying', $actorId);
        }, 5);
    }

    /** @param array<string,mixed> $payload */
    public function receiveWebhook(int $companyId, int $connectionId, string $provider, string $eventId, string $eventType, array $payload, string $signature): int
    {
        return $this->db->transaction(function () use ($companyId, $connectionId, $provider, $eventId, $eventType, $payload, $signature): int {
            $c = $this->connection($companyId, $connectionId, true);
            $config = $this->json($c, 'configuration');
            $secret = is_string($config['webhook_secret'] ?? null) ? $config['webhook_secret'] : '';
            $expected = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);
            if ($secret === '' || ! hash_equals($expected, $signature)) {
                throw ValidationException::withMessages(['signature' => 'Invalid webhook signature.']);
            }$existing = $this->db->table('integration_webhook_receipts')->where(['company_id' => $companyId, 'provider' => $provider, 'event_id' => $eventId])->lockForUpdate()->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }$checksum = IntegrationChecksum::make($payload);
            $receipt = (int) $this->db->table('integration_webhook_receipts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'connection_id' => $connectionId, 'provider' => $provider, 'event_id' => $eventId, 'signature_fingerprint' => hash('sha256', $signature), 'payload_checksum' => $checksum, 'status' => 'accepted', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->db->table('integration_inbound_messages')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'connection_id' => $connectionId, 'provider' => $provider, 'event_id' => $eventId, 'event_type' => $eventType, 'status' => 'received', 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => $checksum, 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return $receipt;
        }, 5);
    }

    private function deadLetter(int $companyId, int $messageId, string $code, string $message): void
    {
        $this->db->table('integration_dead_letters')->insertOrIgnore(['company_id' => $companyId, 'outbound_message_id' => $messageId, 'reason_code' => $code, 'safe_reason' => $message, 'created_at' => now(), 'updated_at' => now()]);
        $this->db->table('integration_outbound_messages')->where('id', $messageId)->update(['status' => 'dead_letter', 'updated_at' => now()]);
    }

    private function history(int $companyId, string $type, int $id, string $action, ?string $from, ?string $to, ?int $actor): void
    {
        $payload = ['type' => $type, 'id' => $id, 'action' => $action, 'from' => $from, 'to' => $to];
        $this->db->table('integration_histories')->insert(['company_id' => $companyId, 'aggregate_type' => $type, 'aggregate_id' => $id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor, 'metadata' => '{}', 'checksum' => IntegrationChecksum::make($payload), 'occurred_at' => now()]);
        $this->db->table('integration_outbox')->insertOrIgnore(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'event_type' => 'integration.'.$action, 'aggregate_type' => $type, 'aggregate_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => IntegrationChecksum::make($payload), 'idempotency_key' => (string) Str::uuid(), 'available_at' => now(), 'created_at' => now()]);
    }

    private function connection(int $companyId, int $id, bool $lock = false): object
    {
        $q = $this->db->table('integration_connections')->where(['company_id' => $companyId, 'id' => $id]);
        $row = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless(is_object($row), 404);

        return $row;
    }

    private function message(int $companyId, int $id, bool $lock = false): object
    {
        $q = $this->db->table('integration_outbound_messages')->where(['company_id' => $companyId, 'id' => $id]);
        $row = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless(is_object($row), 404);

        return $row;
    }

    private function i(object $o, string $k): int
    {
        $v = $o->{$k} ?? 0;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function s(object $o, string $k): string
    {
        $v = $o->{$k} ?? '';

        return is_scalar($v) ? (string) $v : '';
    }

    /** @return array<string,mixed> */
    private function json(object $o, string $k): array
    {
        $v = json_decode($this->s($o, $k), true);

        if (! is_array($v)) {
            return [];
        }
        $result = [];
        foreach ($v as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param array<string,mixed> $data */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([$key => 'A string value is required.']);
        }

        return $value;
    }
}
