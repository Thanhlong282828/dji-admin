<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Performance\Application\DTOs\CareerActionData;

final readonly class ReadinessAssessmentService
{
    public function __construct(private CareerSupport $support) {}

    public function assess(CareerActionData $d): int
    {
        $score = is_numeric($d->score) ? (float) $d->score : -1;
        if ($score < 0 || $score > 100) {
            throw ValidationException::withMessages(['score' => 'Readiness score phải từ 0 đến 100.']);
        }

        return (int) DB::table('readiness_assessments')->insertGetId(['company_id' => $d->companyId, 'employee_id' => $d->employeeId, 'succession_candidate_id' => $d->candidateId, 'assessment_type' => 'succession', 'score' => number_format($score, 2, '.', ''), 'assessed_by' => $d->actorId, 'checksum' => $this->support->checksum(['score' => $score, 'level' => $d->readinessLevel]), 'idempotency_key' => $d->idempotencyKey, 'created_at' => now()]);
    }
}
