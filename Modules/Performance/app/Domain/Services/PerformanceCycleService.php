<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Performance\Application\DTOs\PerformanceActionData;

final readonly class PerformanceCycleService
{
    public function __construct(private PerformanceSupport $support) {}

    public function create(PerformanceActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $existing = DB::table('performance_cycles')->where('company_id', $d->companyId)->where('idempotency_key', $d->idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            } if ($d->periodStart === null || $d->periodEnd === null || $d->name === null || $d->periodEnd->isBefore($d->periodStart)) {
                throw ValidationException::withMessages(['period_end' => 'Chu kỳ đánh giá không hợp lệ.']);
            } $payload = ['name' => $d->name, 'start' => $d->periodStart->toDateString(), 'end' => $d->periodEnd->toDateString()];
            $id = (int) DB::table('performance_cycles')->insertGetId(['company_id' => $d->companyId, 'name' => $d->name, 'starts_at' => $payload['start'], 'ends_at' => $payload['end'], 'rating_scale' => json_encode(['min' => 0, 'max' => 5], JSON_THROW_ON_ERROR), 'period_start' => $payload['start'], 'period_end' => $payload['end'], 'status' => 'draft', 'talent_status' => 'draft', 'created_by' => $d->actorId, 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
            $this->support->outbox($d->companyId, 'PerformanceCycleStarted', 'performance_cycle', $id, $payload);

            return $id;
        }, 3);
    }

    public function close(PerformanceActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $cycle = DB::table('performance_cycles')->where('company_id', $d->companyId)->where('id', $d->cycleId)->lockForUpdate()->first();
            if ($cycle === null) {
                abort(404);
            }
            $values = get_object_vars($cycle);
            $id = is_int($values['id'] ?? null) ? $values['id'] : 0;
            $status = is_string($values['talent_status'] ?? null) ? $values['talent_status'] : '';
            if ($status === 'closed') {
                return $id;
            } if ($status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Chỉ chu kỳ approved mới được đóng.']);
            } DB::table('performance_cycles')->where('id', $id)->update(['status' => 'closed', 'talent_status' => 'closed', 'updated_at' => now()]);
            $this->support->outbox($d->companyId, 'PerformanceClosed', 'performance_cycle', $id, ['status' => 'closed']);

            return $id;
        }, 3);
    }
}
