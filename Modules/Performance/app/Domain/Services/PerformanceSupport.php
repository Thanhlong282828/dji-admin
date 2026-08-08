<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PerformanceSupport
{
    /** @param array<string,mixed> $payload */
    public function checksum(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string,mixed> $payload */
    public function outbox(int $companyId, string $type, string $aggregate, int $id, array $payload): void
    {
        $checksum = $this->checksum($payload);
        DB::table('talent_management_outbox')->insert(['event_id' => (string) Str::uuid(), 'company_id' => $companyId, 'event_type' => $type, 'aggregate_type' => $aggregate, 'aggregate_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'payload_checksum' => $checksum, 'occurred_at' => now(), 'created_at' => now()]);
    }
}
