<?php

namespace Modules\Iam\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AccessReviewService
{
    public function __construct(private IamGovernanceService $governance) {}

    public function create(int $companyId, string $name, string $scopeType, ?int $scopeId, string $dueDate, int $actorId, string $idempotencyKey): int
    {
        return DB::transaction(function () use ($companyId, $name, $scopeType, $scopeId, $dueDate, $actorId, $idempotencyKey): int {
            $existing = DB::table('iam_access_reviews')->where('uuid', $idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $assignments = DB::table('iam_access_assignments')->where(['company_id' => $companyId, 'status' => 'active'])
                ->when($scopeType === 'user' && $scopeId !== null, fn ($query) => $query->where('user_id', $scopeId))
                ->when($scopeType === 'privileged', fn ($query) => $query->where('privileged', true))->orderBy('id')->get();
            $snapshots = [];
            foreach ($assignments as $assignment) {
                $assignmentId = IamValue::int($assignment->id);
                $userId = IamValue::int($assignment->user_id);
                $snapshot = $this->userSnapshot($companyId, $userId, $assignmentId);
                $snapshots[] = $snapshot;
            }
            $campaignPayload = ['company_id' => $companyId, 'name' => $name, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'due_date' => $dueDate, 'snapshots' => $snapshots];
            $reviewId = DB::table('iam_access_reviews')->insertGetId(['uuid' => $idempotencyKey, 'company_id' => $companyId, 'name' => $name, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'status' => 'draft', 'due_date' => $dueDate, 'version' => 1, 'snapshot_checksum' => IamChecksum::make($campaignPayload), 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($snapshots as $snapshot) {
                $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $checksum = IamChecksum::make($snapshot);
                DB::table('iam_access_review_items')->insert(['access_review_id' => $reviewId, 'access_assignment_id' => $snapshot['assignment_id'], 'decision' => 'pending', 'version' => 1, 'snapshot_checksum' => $checksum, 'checksum' => $checksum, 'snapshot' => $json, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->history($companyId, 'access_review', $reviewId, 'created', $actorId, ['status' => 'draft']);
            $this->outbox($companyId, 'iam.review.created', 'access_review', $reviewId, ['review_id' => $reviewId]);

            return $reviewId;
        }, 3);
    }

    public function start(int $companyId, int $reviewId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $reviewId, $actorId): void {
            $review = $this->lockedReview($companyId, $reviewId);
            $status = IamValue::string($review->status);
            if ($status === 'active' || $status === 'under_review') {
                return;
            }
            if ($status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft campaigns can start.']);
            }
            DB::table('iam_access_reviews')->where('id', $reviewId)->update(['status' => 'active', 'version' => IamValue::int($review->version) + 1, 'updated_at' => now()]);
            $this->history($companyId, 'access_review', $reviewId, 'started', $actorId, ['status' => 'active']);
            $this->outbox($companyId, 'iam.review.assigned', 'access_review', $reviewId, ['review_id' => $reviewId, 'due_date' => IamValue::string($review->due_date)]);
        }, 3);
    }

    public function decide(int $companyId, int $reviewId, int $itemId, int $reviewerId, string $decision, string $reason, int $expectedVersion): void
    {
        DB::transaction(function () use ($companyId, $reviewId, $itemId, $reviewerId, $decision, $reason, $expectedVersion): void {
            $review = $this->lockedReview($companyId, $reviewId);
            if (! in_array(IamValue::string($review->status), ['active', 'under_review'], true)) {
                throw ValidationException::withMessages(['status' => 'Campaign is not accepting decisions.']);
            }
            $item = DB::table('iam_access_review_items')->where(['id' => $itemId, 'access_review_id' => $reviewId])->lockForUpdate()->first();
            abort_unless($item instanceof \stdClass, 404);
            if (IamValue::string($item->decision) !== 'pending') {
                if (IamValue::string($item->decision) === $decision) {
                    return;
                }
                throw ValidationException::withMessages(['decision' => 'Review item already has a terminal decision.']);
            }
            if (IamValue::int($item->version) !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Review item version conflict.']);
            }
            $payload = ['item_id' => $itemId, 'decision' => $decision, 'reason' => $reason, 'reviewer_id' => $reviewerId, 'version' => $expectedVersion + 1];
            DB::table('iam_access_review_items')->where('id', $itemId)->update(['decision' => $decision, 'reason' => $reason, 'reviewer_id' => $reviewerId, 'decided_at' => now(), 'version' => $expectedVersion + 1, 'checksum' => IamChecksum::make($payload), 'updated_at' => now()]);
            DB::table('iam_access_reviews')->where('id', $reviewId)->where('status', 'active')->update(['status' => 'under_review', 'version' => IamValue::int($review->version) + 1, 'updated_at' => now()]);
            $this->history($companyId, 'access_review_item', $itemId, 'decided.'.$decision, $reviewerId, $payload);
        }, 3);
    }

    public function complete(int $companyId, int $reviewId, int $actorId): int
    {
        return DB::transaction(function () use ($companyId, $reviewId, $actorId): int {
            $review = $this->lockedReview($companyId, $reviewId);
            if (IamValue::string($review->status) === 'completed') {
                $existing = DB::table('iam_certifications')->where('access_review_id', $reviewId)->value('id');

                return IamValue::int($existing);
            }
            if (DB::table('iam_access_review_items')->where('access_review_id', $reviewId)->where('decision', 'pending')->exists()) {
                throw ValidationException::withMessages(['items' => 'All review items require a decision.']);
            }
            $items = DB::table('iam_access_review_items')->where('access_review_id', $reviewId)->orderBy('id')->lockForUpdate()->get();
            $snapshot = [];
            foreach ($items as $item) {
                $stored = json_decode(IamValue::string($item->snapshot), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($stored)) {
                    throw new \UnexpectedValueException('Invalid certification snapshot.');
                }
                $snapshot[] = ['item_id' => IamValue::int($item->id), 'decision' => IamValue::string($item->decision), 'reason' => is_string($item->reason) ? $item->reason : null, 'snapshot' => $stored];
                if (IamValue::string($item->decision) === 'revoke') {
                    $this->governance->revoke($companyId, IamValue::int($item->access_assignment_id), $actorId);
                }
            }
            $version = IamValue::int($review->version) + 1;
            $checksum = IamChecksum::make(['review_id' => $reviewId, 'version' => $version, 'items' => $snapshot]);
            $certificationId = DB::table('iam_certifications')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'access_review_id' => $reviewId, 'version' => $version, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'checksum' => $checksum, 'certified_by' => $actorId, 'certified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('iam_access_reviews')->where('id', $reviewId)->update(['status' => 'completed', 'version' => $version, 'updated_at' => now()]);
            $this->history($companyId, 'access_review', $reviewId, 'completed', $actorId, ['certification_id' => $certificationId, 'checksum' => $checksum]);
            $this->outbox($companyId, 'iam.review.completed', 'access_review', $reviewId, ['review_id' => $reviewId, 'certification_id' => $certificationId]);

            return $certificationId;
        }, 3);
    }

    private function lockedReview(int $companyId, int $reviewId): \stdClass
    {
        $review = DB::table('iam_access_reviews')->where(['id' => $reviewId, 'company_id' => $companyId])->lockForUpdate()->first();
        abort_unless($review instanceof \stdClass, 404);

        return $review;
    }

    /** @return array{assignment_id:int,user:array{id:int,name:string,email:string},company_id:int,roles:list<string>,direct_permissions:list<string>,assignment:array{type:string,id:int,privileged:bool,effective_from:string,effective_to:string|null}} */
    private function userSnapshot(int $companyId, int $userId, int $assignmentId): array
    {
        $user = DB::table('users')->where('id', $userId)->first();
        $assignment = DB::table('iam_access_assignments')->where(['id' => $assignmentId, 'company_id' => $companyId, 'user_id' => $userId])->first();
        abort_unless($user instanceof \stdClass && $assignment instanceof \stdClass, 404);
        $roles = array_values(DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')->where(['mr.model_type' => 'App\\Models\\User', 'mr.model_id' => $userId])->orderBy('r.name')->pluck('r.name')->filter(fn (mixed $value): bool => is_string($value))->values()->all());
        $permissions = array_values(DB::table('model_has_permissions as mp')->join('permissions as p', 'p.id', '=', 'mp.permission_id')->where(['mp.model_type' => 'App\\Models\\User', 'mp.model_id' => $userId])->orderBy('p.name')->pluck('p.name')->filter(fn (mixed $value): bool => is_string($value))->values()->all());

        return ['assignment_id' => $assignmentId, 'user' => ['id' => $userId, 'name' => IamValue::string($user->name), 'email' => IamValue::string($user->email)], 'company_id' => $companyId, 'roles' => $roles, 'direct_permissions' => $permissions, 'assignment' => ['type' => IamValue::string($assignment->entitlement_type), 'id' => IamValue::int($assignment->entitlement_id), 'privileged' => (bool) $assignment->privileged, 'effective_from' => IamValue::string($assignment->effective_from), 'effective_to' => is_string($assignment->effective_to) ? $assignment->effective_to : null]];
    }

    /** @param array<string, mixed> $payload */
    private function history(int $companyId, string $type, int $id, string $action, int $actorId, array $payload): void
    {
        DB::table('iam_governance_histories')->insert(['company_id' => $companyId, 'aggregate_type' => $type, 'aggregate_id' => $id, 'action' => $action, 'actor_id' => $actorId, 'before' => null, 'after' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => IamChecksum::make($payload), 'occurred_at' => now()]);
    }

    /** @param array<string, mixed> $payload */
    private function outbox(int $companyId, string $event, string $type, int $id, array $payload): void
    {
        $idempotency = (string) Str::uuid();
        DB::table('iam_outbox')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'event_type' => $event, 'aggregate_type' => $type, 'aggregate_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => IamChecksum::make($payload), 'idempotency_key' => $idempotency, 'attempts' => 0, 'available_at' => now(), 'created_at' => now()]);
    }
}
