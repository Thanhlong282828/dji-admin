<?php

namespace Modules\Audit\Infrastructure\Ingestion;

use Illuminate\Database\Query\Builder;
use Modules\Audit\Application\DTOs\SourceAuditEnvelope;
use stdClass;

final readonly class PayrollAuditConsumer extends AbstractSourceAuditConsumer
{
    protected function sourceQuery(): Builder
    {
        return $this->db->table('activity_log')->where('log_name', 'like', 'payroll-%');
    }

    protected function consumerName(): string
    {
        return 'enterprise-audit-payroll-v1';
    }

    protected function envelope(stdClass $row): SourceAuditEnvelope
    {
        $v = get_object_vars($row);
        $properties = $this->json($v['properties'] ?? null);
        $changes = $this->json($v['attribute_changes'] ?? null);
        $id = $this->text($v['id'] ?? null);
        $eventId = 'payroll-activity-'.$id;
        $before = $this->stringKeys($changes['old'] ?? null);
        $after = $this->stringKeys($changes['attributes'] ?? null);
        if ($after === []) {
            $after = $properties;
        }

        return new SourceAuditEnvelope($eventId, $this->integer($properties['company_id'] ?? null) ?? 0, 'Payroll', 'payroll.'.$this->text($v['event'] ?? null, 'changed'), $this->text($v['subject_type'] ?? null, $this->text($v['log_name'] ?? null, 'payroll_resource')), $this->text($v['subject_id'] ?? $properties['run_id'] ?? $properties['snapshot_id'] ?? $id), 'user', $this->integer($v['causer_id'] ?? null), $before, $after, ['log_name' => $this->text($v['log_name'] ?? null), 'description' => $this->text($v['description'] ?? null)], $this->text($properties['correlation_id'] ?? null, $eventId), $eventId, $this->date($v['created_at'] ?? null));
    }

    /** @return array<string, mixed> */
    private function stringKeys(mixed $value): array
    {
        return is_array($value) ? array_filter($value, static fn (int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY) : [];
    }
}
