<?php

namespace Modules\Iam\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Iam\Domain\Contracts\AccessProvisioningAdapterInterface;

final readonly class IamGovernanceService
{
    public function __construct(private AccessProvisioningAdapterInterface $adapter, private SegregationOfDutiesService $sod) {}

    /** @param list<array{type:string,id:int,effective_to:string|null}> $items */
    public function create(int $companyId, int $requesterId, int $subjectId, string $reason, string $key, array $items): int
    {
        abort_unless(DB::table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $requesterId])->exists(), 404);
        abort_unless(DB::table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $subjectId])->exists(), 404);

        return DB::transaction(function () use ($companyId, $requesterId, $subjectId, $reason, $key, $items): int {
            $existing = DB::table('iam_access_requests')->where('idempotency_key', $key)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $payload = ['company_id' => $companyId, 'requester_id' => $requesterId, 'subject_user_id' => $subjectId, 'reason' => $reason];
            $id = DB::table('iam_access_requests')->insertGetId([...$payload, 'uuid' => (string) Str::uuid(), 'status' => 'requested', 'idempotency_key' => $key, 'version' => 1, 'checksum' => IamChecksum::make($payload), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($items as $item) {
                $risk = $this->risk($item['type'], $item['id']);
                DB::table('iam_access_request_items')->insert(['access_request_id' => $id, 'entitlement_type' => $item['type'], 'entitlement_id' => $item['id'], 'risk_level' => $risk, 'effective_from' => now(), 'effective_to' => $item['effective_to'], 'checksum' => IamChecksum::make($item), 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->record($companyId, 'access_request', $id, 'requested', $requesterId, null, $payload);

            return $id;
        });
    }

    public function transition(int $companyId, int $requestId, int $actorId, string $action, ?string $reason = null): void
    {
        if ($action === 'approve') {
            $request = DB::table('iam_access_requests')->where(['id' => $requestId, 'company_id' => $companyId])->first();
            abort_unless($request instanceof \stdClass, 404);
            foreach (DB::table('iam_access_request_items')->where('access_request_id', $requestId)->get() as $item) {
                if ($this->sod->detect($companyId, IamValue::int($request->subject_user_id), IamValue::string($item->entitlement_type), IamValue::int($item->entitlement_id), $requestId)) {
                    throw ValidationException::withMessages(['entitlement' => 'Blocking segregation of duties conflict.']);
                }
            }
        }
        DB::transaction(function () use ($companyId, $requestId, $actorId, $action, $reason): void {
            $r = DB::table('iam_access_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($r instanceof \stdClass, 404);
            $map = ['submit' => ['requested', 'requested'], 'review' => ['requested', 'reviewed'], 'approve' => ['reviewed', 'approved'], 'reject' => ['requested|reviewed', 'rejected']];
            if (! isset($map[$action])) {
                throw ValidationException::withMessages(['action' => 'Unsupported transition.']);
            } [$allowed,$to] = $map[$action];
            if (! in_array($r->status, explode('|', $allowed), true)) {
                throw ValidationException::withMessages(['status' => 'Invalid access request transition.']);
            }
            if ($action === 'approve' && IamValue::int($r->requester_id) === $actorId) {
                throw ValidationException::withMessages(['actor' => 'Requester cannot approve own access request.']);
            }
            if ($action === 'review' && IamValue::int($r->requester_id) === $actorId) {
                throw ValidationException::withMessages(['actor' => 'Requester cannot review own access request.']);
            }
            if ($action === 'approve' && IamValue::int($r->reviewed_by) === $actorId) {
                throw ValidationException::withMessages(['actor' => 'Reviewer cannot approve the same privileged request.']);
            }
            $updates = ['status' => $to, 'version' => IamValue::int($r->version) + 1, 'updated_at' => now()];
            if ($action === 'review') {
                $updates += ['reviewed_by' => $actorId, 'reviewed_at' => now()];
            }
            if ($action === 'approve') {
                $updates += ['approved_by' => $actorId, 'approved_at' => now()];
            }
            if ($action === 'reject') {
                $updates += ['rejected_at' => now()];
            }
            DB::table('iam_access_requests')->where('id', $requestId)->update($updates);
            $from = IamValue::string($r->status);
            $this->record($companyId, 'access_request', $requestId, $action, $actorId, ['status' => $from], ['status' => $to, 'reason' => $reason]);
            $this->outbox($companyId, 'iam.access.'.$to, 'access_request', $requestId, ['request_id' => $requestId, 'subject_user_id' => IamValue::int($r->subject_user_id), 'status' => $to]);
        });
    }

    public function attachWorkflow(int $companyId, int $requestId, int $actorId, int $workflowInstanceId): void
    {
        DB::transaction(function () use ($companyId, $requestId, $actorId, $workflowInstanceId): void {
            $request = DB::table('iam_access_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($request instanceof \stdClass, 404);
            if (is_numeric($request->workflow_instance_id)) {
                abort_unless(IamValue::int($request->workflow_instance_id) === $workflowInstanceId, 409, 'Access request already belongs to another workflow.');

                return;
            }
            abort_unless(IamValue::string($request->status) === 'requested', 422, 'Only requested access can enter workflow.');
            DB::table('iam_access_requests')->where('id', $requestId)->update(['approval_mode' => 'workflow', 'workflow_instance_id' => $workflowInstanceId, 'workflow_version' => 1, 'version' => IamValue::int($request->version) + 1, 'updated_at' => now()]);
            $this->record($companyId, 'access_request', $requestId, 'workflow.started', $actorId, ['status' => 'requested'], ['workflow_instance_id' => $workflowInstanceId]);
            $this->outbox($companyId, 'iam.access.submitted', 'access_request', $requestId, ['request_id' => $requestId, 'subject_user_id' => IamValue::int($request->subject_user_id), 'workflow_instance_id' => $workflowInstanceId]);
        }, 3);
    }

    public function completeWorkflow(int $companyId, int $requestId, int $workflowInstanceId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $requestId, $workflowInstanceId, $actorId): void {
            $request = DB::table('iam_access_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($request instanceof \stdClass, 404);
            abort_unless(IamValue::string($request->approval_mode) === 'workflow' && IamValue::int($request->workflow_instance_id) === $workflowInstanceId, 409, 'Workflow subject/version mismatch.');
            if (IamValue::string($request->status) === 'provisioned') {
                return;
            }
            abort_unless(in_array(IamValue::string($request->status), ['requested', 'reviewed', 'approved'], true), 422, 'IAM request is not eligible for workflow completion.');
            if (IamValue::string($request->status) !== 'approved') {
                DB::table('iam_access_requests')->where('id', $requestId)->update(['status' => 'approved', 'reviewed_by' => $actorId, 'reviewed_at' => now(), 'approved_by' => $actorId, 'approved_at' => now(), 'workflow_version' => IamValue::int($request->workflow_version) + 1, 'version' => IamValue::int($request->version) + 1, 'updated_at' => now()]);
                $this->record($companyId, 'access_request', $requestId, 'workflow.approved', $actorId, ['status' => IamValue::string($request->status)], ['status' => 'approved', 'workflow_instance_id' => $workflowInstanceId]);
            }
        }, 3);
        $this->provision($companyId, $requestId, $actorId);
    }

    public function rejectWorkflow(int $companyId, int $requestId, int $workflowInstanceId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $requestId, $workflowInstanceId, $actorId): void {
            $request = DB::table('iam_access_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($request instanceof \stdClass, 404);
            abort_unless(IamValue::string($request->approval_mode) === 'workflow' && IamValue::int($request->workflow_instance_id) === $workflowInstanceId, 409, 'Workflow subject/version mismatch.');
            if (IamValue::string($request->status) === 'rejected') {
                return;
            }
            abort_unless(in_array(IamValue::string($request->status), ['requested', 'reviewed'], true), 422);
            DB::table('iam_access_requests')->where('id', $requestId)->update(['status' => 'rejected', 'rejected_at' => now(), 'workflow_version' => IamValue::int($request->workflow_version) + 1, 'version' => IamValue::int($request->version) + 1, 'updated_at' => now()]);
            $this->record($companyId, 'access_request', $requestId, 'workflow.rejected', $actorId, ['status' => IamValue::string($request->status)], ['status' => 'rejected']);
            $this->outbox($companyId, 'iam.access.rejected', 'access_request', $requestId, ['request_id' => $requestId, 'subject_user_id' => IamValue::int($request->subject_user_id)]);
        }, 3);
    }

    public function provision(int $companyId, int $requestId, int $actorId): void
    {
        $preflight = DB::table('iam_access_requests')->where(['id' => $requestId, 'company_id' => $companyId])->first();
        abort_unless($preflight instanceof \stdClass, 404);
        foreach (DB::table('iam_access_request_items')->where('access_request_id', $requestId)->get() as $item) {
            $this->sod->assertProvisionable($companyId, IamValue::int($preflight->subject_user_id), IamValue::string($item->entitlement_type), IamValue::int($item->entitlement_id), $requestId);
        }
        DB::transaction(function () use ($companyId, $requestId, $actorId): void {
            $r = DB::table('iam_access_requests')->where(['id' => $requestId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($r instanceof \stdClass, 404);
            if ($r->status === 'provisioned') {
                return;
            } if ($r->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Only approved requests can be provisioned.']);
            }
            $items = DB::table('iam_access_request_items')->where('access_request_id', $requestId)->get();
            foreach ($items as $item) {
                $subjectId = IamValue::int($r->subject_user_id);
                $entitlementType = IamValue::string($item->entitlement_type);
                $entitlementId = IamValue::int($item->entitlement_id);
                $this->adapter->provision($companyId, $subjectId, $entitlementType, $entitlementId);
                $privileged = $item->risk_level === 'privileged';
                if ($privileged && $item->effective_to === null) {
                    throw ValidationException::withMessages(['effective_to' => 'Privileged access must expire.']);
                }
                $payload = ['company_id' => $companyId, 'user_id' => $subjectId, 'entitlement_type' => $entitlementType, 'entitlement_id' => $entitlementId];
                DB::table('iam_access_assignments')->insertOrIgnore([...$payload, 'uuid' => (string) Str::uuid(), 'access_request_item_id' => IamValue::int($item->id), 'status' => 'active', 'privileged' => $privileged, 'reason' => IamValue::string($r->reason), 'requested_by' => $r->requester_id, 'reviewed_by' => $r->reviewed_by, 'effective_from' => $item->effective_from ?? now(), 'effective_to' => $item->effective_to, 'approved_by' => $r->approved_by, 'version' => 1, 'checksum' => IamChecksum::make($payload), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('iam_access_requests')->where('id', $requestId)->update(['status' => 'provisioned', 'provisioned_by' => $actorId, 'provisioned_at' => now(), 'version' => IamValue::int($r->version) + 1, 'updated_at' => now()]);
            $this->record($companyId, 'access_request', $requestId, 'provisioned', $actorId, ['status' => 'approved'], ['status' => 'provisioned']);
            $this->outbox($companyId, 'iam.access.provisioned', 'access_request', $requestId, ['request_id' => $requestId, 'subject_user_id' => IamValue::int($r->subject_user_id)]);
        }, 3);
    }

    public function revoke(int $companyId, int $assignmentId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $assignmentId, $actorId): void {
            $a = DB::table('iam_access_assignments')->where(['id' => $assignmentId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($a instanceof \stdClass, 404);
            if ($a->status === 'revoked') {
                return;
            }$userId = IamValue::int($a->user_id);
            $entitlementType = IamValue::string($a->entitlement_type);
            $entitlementId = IamValue::int($a->entitlement_id);
            $this->adapter->revoke($companyId, $userId, $entitlementType, $entitlementId);
            DB::table('iam_access_assignments')->where('id', $assignmentId)->update(['status' => 'revoked', 'revoked_by' => $actorId, 'revoked_at' => now(), 'version' => IamValue::int($a->version) + 1, 'updated_at' => now()]);
            $this->record($companyId, 'assignment', $assignmentId, 'revoked', $actorId, ['status' => IamValue::string($a->status)], ['status' => 'revoked']);
            $this->outbox($companyId, 'iam.access.revoked', 'assignment', $assignmentId, ['assignment_id' => $assignmentId, 'user_id' => $userId]);
        }, 3);
    }

    public function expire(int $companyId, int $assignmentId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $assignmentId, $actorId): void {
            $assignment = DB::table('iam_access_assignments')->where(['id' => $assignmentId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($assignment instanceof \stdClass, 404);
            $status = IamValue::string($assignment->status);
            if ($status === 'expired') {
                return;
            }
            if ($status !== 'active' || ! is_string($assignment->effective_to) || now()->lt($assignment->effective_to)) {
                throw ValidationException::withMessages(['assignment' => 'Assignment is not eligible for expiry.']);
            }
            $userId = IamValue::int($assignment->user_id);
            $type = IamValue::string($assignment->entitlement_type);
            $entitlementId = IamValue::int($assignment->entitlement_id);
            $this->adapter->revoke($companyId, $userId, $type, $entitlementId);
            DB::table('iam_access_assignments')->where('id', $assignmentId)->update(['status' => 'expired', 'revoked_by' => $actorId, 'revoked_at' => now(), 'version' => IamValue::int($assignment->version) + 1, 'updated_at' => now()]);
            $this->record($companyId, 'assignment', $assignmentId, 'expired', $actorId, ['status' => $status], ['status' => 'expired']);
            $this->outbox($companyId, 'iam.access.expired', 'assignment', $assignmentId, ['assignment_id' => $assignmentId, 'user_id' => $userId]);
        }, 3);
    }

    private function risk(string $type, int $id): string
    {
        if ($type === 'role' && DB::table('iam_privileged_roles')->where('role_id', $id)->exists()) {
            return 'privileged';
        }$rawName = $type === 'role' ? DB::table('roles')->where('id', $id)->value('name') : DB::table('permissions')->where('id', $id)->value('name');
        $name = is_string($rawName) ? $rawName : '';

        return preg_match('/payroll|tax|bank|government\.filing\.submit|erp\.integration\.export\.execute|iam\.request\.approve/', $name) ? 'restricted' : 'standard';
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function record(int $company, string $type, int $id, string $action, int $actor, ?array $before, ?array $after): void
    {
        $payload = ['company' => $company, 'type' => $type, 'id' => $id, 'action' => $action, 'actor' => $actor, 'before' => $before, 'after' => $after];
        DB::table('iam_governance_histories')->insert(['company_id' => $company, 'aggregate_type' => $type, 'aggregate_id' => $id, 'action' => $action, 'actor_id' => $actor, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR), 'checksum' => IamChecksum::make($payload), 'occurred_at' => now()]);
    }

    /** @param array<string,mixed> $payload */
    private function outbox(int $company, string $event, string $type, int $id, array $payload): void
    {
        $key = (string) Str::uuid();
        DB::table('iam_outbox')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'event_type' => $event, 'aggregate_type' => $type, 'aggregate_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => IamChecksum::make($payload), 'idempotency_key' => $key, 'attempts' => 0, 'available_at' => now(), 'created_at' => now()]);
    }
}
