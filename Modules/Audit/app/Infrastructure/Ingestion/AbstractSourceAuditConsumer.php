<?php

namespace Modules\Audit\Infrastructure\Ingestion;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Modules\Audit\Application\DTOs\AuditEventData;
use Modules\Audit\Application\DTOs\SourceAuditEnvelope;
use Modules\Audit\Domain\Contracts\AuditRecorderInterface;
use Modules\Audit\Domain\Contracts\AuditSourceConsumerInterface;
use Modules\Audit\Domain\Support\AuditChecksum;
use stdClass;

abstract readonly class AbstractSourceAuditConsumer implements AuditSourceConsumerInterface
{
    public function __construct(protected ConnectionInterface $db, private AuditRecorderInterface $recorder) {}

    final public function consume(int $sourceRowId): ?int
    {
        $candidate = $this->sourceQuery()->where('id', $sourceRowId)->first();
        if (! $candidate instanceof stdClass) {
            return null;
        }
        $candidateEnvelope = $this->envelope($candidate);

        return $this->db->transaction(function () use ($sourceRowId, $candidateEnvelope): ?int {
            $this->db->table('companies')->where('id', $candidateEnvelope->companyId)->lockForUpdate()->first();
            $row = $this->sourceQuery()->where('id', $sourceRowId)->lockForUpdate()->first();
            if (! $row instanceof stdClass) {
                return null;
            }
            $envelope = $this->envelope($row);
            $checkpoint = $this->db->table('audit_source_checkpoints')->where(['source_module' => $envelope->sourceModule, 'source_event_id' => $envelope->sourceEventId, 'consumer_name' => $this->consumerName()])->value('audit_event_id');
            if (is_numeric($checkpoint)) {
                return null;
            }
            $correlation = $this->uuid($envelope->correlationId ?? $envelope->sourceEventId);
            $causation = $this->uuid($envelope->causationId ?? $envelope->sourceEventId);
            $idempotency = 'source:'.strtolower($envelope->sourceModule).':'.$envelope->sourceEventId.':'.$this->consumerName();
            $eventId = $this->recorder->record(new AuditEventData($envelope->companyId, $envelope->actorType, $envelope->actorId, $this->action($envelope->sourceEvent), $envelope->sourceModule, $envelope->sourceEvent, $envelope->resourceType, $envelope->resourceId, $envelope->before, $envelope->after, $envelope->metadata + ['source_event_id' => $envelope->sourceEventId, 'consumer_name' => $this->consumerName()], $idempotency, $correlation, $causation, null, null, $envelope->occurredAt));
            $checkpointPayload = ['source_module' => $envelope->sourceModule, 'source_event_id' => $envelope->sourceEventId, 'consumer_name' => $this->consumerName(), 'audit_event_id' => $eventId];
            $this->db->table('audit_source_checkpoints')->insert(['company_id' => $envelope->companyId, 'source_module' => $envelope->sourceModule, 'source_event_id' => $envelope->sourceEventId, 'consumer_name' => $this->consumerName(), 'audit_event_id' => $eventId, 'checksum' => AuditChecksum::make($checkpointPayload), 'consumed_at' => now()]);

            return $eventId;
        }, 5);
    }

    final public function consumePending(int $limit = 100): int
    {
        $ids = $this->sourceQuery()->orderBy('id')->limit(max(1, $limit))->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            if (is_numeric($id) && $this->consume((int) $id) !== null) {
                $count++;
            }
        }

        return $count;
    }

    abstract protected function sourceQuery(): Builder;

    abstract protected function consumerName(): string;

    abstract protected function envelope(stdClass $row): SourceAuditEnvelope;

    /** @return array<string, mixed> */
    final protected function json(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_filter($decoded, static fn (int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY) : [];
    }

    final protected function text(mixed $value, string $fallback = ''): string
    {
        return is_scalar($value) ? (string) $value : $fallback;
    }

    final protected function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    final protected function date(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse(is_string($value) ? $value : 'now');
    }

    private function action(string $event): string
    {
        $parts = explode('.', $event);

        return substr((string) end($parts), 0, 80);
    }

    private function uuid(string $identity): string
    {
        if (Str::isUuid($identity)) {
            return $identity;
        }
        $hash = md5($this->consumerName().':'.$identity);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
}
