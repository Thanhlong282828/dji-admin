<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Performance\Application\DTOs\PerformanceActionData;
use Modules\Performance\Domain\Enums\PerformanceStatus;

final readonly class PerformanceReviewService
{
    public function __construct(private PerformanceSupport $support, private PerformanceStateMachine $states) {}

    public function start(PerformanceActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $existing = DB::table('performance_reviews')->where('company_id', $d->companyId)->where('idempotency_key', $d->idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            } $employee = DB::table('employees')->where('company_id', $d->companyId)->where('id', $d->employeeId)->exists();
            $cycle = DB::table('performance_cycles')->where('company_id', $d->companyId)->where('id', $d->cycleId)->lockForUpdate()->first();
            if (! $employee || $cycle === null) {
                abort(404);
            }
            if ($d->reviewerId === null || $d->reviewerId < 1) {
                throw ValidationException::withMessages(['reviewer_id' => 'Reviewer là bắt buộc.']);
            }
            $cycleData = get_object_vars($cycle);
            $cycleId = is_int($cycleData['id'] ?? null) ? $cycleData['id'] : 0;
            $creatorId = is_int($cycleData['created_by'] ?? null) ? $cycleData['created_by'] : 0;
            if ($creatorId === $d->reviewerId) {
                throw ValidationException::withMessages(['reviewer_id' => 'Người tạo chu kỳ không được là reviewer.']);
            } $payload = ['employee_id' => $d->employeeId, 'cycle_id' => $d->cycleId, 'reviewer_id' => $d->reviewerId];
            $id = (int) DB::table('performance_reviews')->insertGetId(['company_id' => $d->companyId, 'employee_id' => $d->employeeId, 'cycle_id' => $d->cycleId, 'reviewer_id' => $d->reviewerId, 'status' => 'in_progress', 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('performance_cycles')->where('id', $cycleId)->update(['status' => 'active', 'talent_status' => 'in_progress', 'updated_at' => now()]);
            $this->history($d, $id, null, 'in_progress', null);

            return $id;
        }, 3);
    }

    public function selfReview(PerformanceActionData $d): int
    {
        return $this->transition($d, 'in_progress', 'self_review', null, 'PerformanceReviewSubmitted');
    }

    public function managerReview(PerformanceActionData $d): int
    {
        return $this->transition($d, 'self_review', 'manager_review', $d->rating, 'PerformanceReviewSubmitted');
    }

    public function approve(PerformanceActionData $d): int
    {
        return DB::transaction(function () use ($d): int {
            $r = $this->locked($d);
            if ($r['status'] === 'approved') {
                return $r['id'];
            } if ($r['status'] !== 'manager_review') {
                throw ValidationException::withMessages(['status' => 'Review chưa sẵn sàng phê duyệt.']);
            } if ($r['reviewer_id'] === $d->actorId) {
                throw ValidationException::withMessages(['actor' => 'Reviewer không được tự phê duyệt.']);
            }
            $this->states->validate(PerformanceStatus::ManagerReview, PerformanceStatus::Approved);
            DB::table('performance_reviews')->where('id', $r['id'])->update(['status' => 'approved', 'approved_by' => $d->actorId, 'approved_at' => now(), 'updated_at' => now()]);
            DB::table('performance_cycles')->where('id', $r['cycle_id'])->update(['status' => 'calibration', 'talent_status' => 'approved', 'updated_at' => now()]);
            $this->history($d, $r['id'], 'manager_review', 'approved', $r['overall_rating']);
            $this->support->outbox($d->companyId, 'PerformanceApproved', 'performance_review', $r['id'], ['status' => 'approved']);

            return $r['id'];
        }, 3);
    }

    private function transition(PerformanceActionData $d, string $from, string $to, ?string $rating, string $event): int
    {
        return DB::transaction(function () use ($d, $from, $to, $rating, $event): int {
            $r = $this->locked($d);
            $duplicate = DB::table('performance_review_history')->where('review_id', $r['id'])->where('idempotency_key', $d->idempotencyKey)->exists();
            if ($duplicate) {
                return $r['id'];
            } if ($r['status'] !== $from) {
                throw ValidationException::withMessages(['status' => "Không thể chuyển {$r['status']} sang {$to}."]);
            }
            $this->states->validate(PerformanceStatus::from($from), PerformanceStatus::from($to));
            if ($rating !== null && (! is_numeric($rating) || (float) $rating < 0 || (float) $rating > 5)) {
                throw ValidationException::withMessages(['rating' => 'Rating phải trong khoảng 0–5.']);
            } DB::table('performance_reviews')->where('id', $r['id'])->update(['status' => $to, 'overall_rating' => $rating ?? $r['overall_rating'], 'updated_at' => now()]);
            $this->history($d, $r['id'], $from, $to, $rating);
            $this->support->outbox($d->companyId, $event, 'performance_review', $r['id'], ['from' => $from, 'to' => $to, 'rating' => $rating]);

            return $r['id'];
        }, 3);
    }

    /** @return array{id: int, cycle_id: int, reviewer_id: int, status: string, overall_rating: string|null} */
    private function locked(PerformanceActionData $d): array
    {
        $r = DB::table('performance_reviews')->where('company_id', $d->companyId)->where('id', $d->reviewId)->lockForUpdate()->first();
        if ($r === null) {
            abort(404);
        }
        $values = get_object_vars($r);
        $id = $values['id'] ?? null;
        $cycleId = $values['cycle_id'] ?? null;
        $reviewerId = $values['reviewer_id'] ?? null;
        $status = $values['status'] ?? null;
        $rating = $values['overall_rating'] ?? null;
        if (! is_int($id) || ! is_int($cycleId) || ! is_int($reviewerId) || ! is_string($status) || (! is_string($rating) && $rating !== null)) {
            throw ValidationException::withMessages(['review' => 'Dữ liệu review không hợp lệ.']);
        }

        return ['id' => $id, 'cycle_id' => $cycleId, 'reviewer_id' => $reviewerId, 'status' => $status, 'overall_rating' => $rating];
    }

    private function history(PerformanceActionData $d, int $review, ?string $from, string $to, ?string $rating): void
    {
        $payload = ['from' => $from, 'to' => $to, 'rating' => $rating];
        DB::table('performance_review_history')->insert(['company_id' => $d->companyId, 'review_id' => $review, 'from_status' => $from, 'to_status' => $to, 'rating' => $rating, 'actor_id' => $d->actorId, 'checksum' => $this->support->checksum($payload), 'idempotency_key' => $d->idempotencyKey, 'created_at' => now()]);
    }
}
