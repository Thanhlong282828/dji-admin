<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CareerSupport
{
    /** @param array<string,mixed> $payload */
    public function checksum(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @param array<string,mixed> $payload */
    public function record(int $company, int $actor, string $key, string $type, int $id, string $event, ?int $employee, array $payload): void
    {
        $checksum = $this->checksum($payload);
        DB::table('career_history')->insert(['company_id' => $company, 'employee_id' => $employee, 'aggregate_type' => $type, 'aggregate_id' => $id, 'event_type' => $event, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'actor_id' => $actor, 'checksum' => $checksum, 'idempotency_key' => $key, 'created_at' => now()]);
        DB::table('career_outbox')->insert(['event_id' => (string) Str::uuid(), 'company_id' => $company, 'event_type' => $event, 'aggregate_type' => $type, 'aggregate_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => $checksum, 'occurred_at' => now()]);
    }
}
