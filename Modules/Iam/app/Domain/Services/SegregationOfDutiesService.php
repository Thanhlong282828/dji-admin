<?php

namespace Modules\Iam\Domain\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SegregationOfDutiesService
{
    public function assertProvisionable(int $companyId, int $userId, string $type, int $id, int $requestId): void
    {
        if ($this->detect($companyId, $userId, $type, $id, $requestId)) {
            throw ValidationException::withMessages(['entitlement' => 'Segregation of duties conflict blocks provisioning.']);
        }
    }

    public function detect(int $companyId, int $userId, string $type, int $id, int $requestId): bool
    {
        $blocking = false;
        $currentRoles = DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->where('model_id', $userId)->pluck('role_id')->map(fn (mixed $v): int => IamValue::int($v))->all();
        $currentPermissions = DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->where('model_id', $userId)->pluck('permission_id')->map(fn (mixed $v): int => IamValue::int($v))->all();
        $rules = DB::table('iam_sod_rules')->where('active', true)->where(fn (Builder $q): Builder => $q->whereNull('company_id')->orWhere('company_id', $companyId))->get();
        foreach ($rules as $rule) {
            $leftType = IamValue::string($rule->left_type);
            $rightType = IamValue::string($rule->right_type);
            $leftId = IamValue::int($rule->left_id);
            $rightId = IamValue::int($rule->right_id);
            $left = $leftType === 'role' ? in_array($leftId, $currentRoles, true) : in_array($leftId, $currentPermissions, true);
            $right = $rightType === $type && $rightId === $id;
            $reverse = $leftType === $type && $leftId === $id && ($rightType === 'role' ? in_array($rightId, $currentRoles, true) : in_array($rightId, $currentPermissions, true));
            if (($left && $right) || $reverse) {
                $ruleId = IamValue::int($rule->id);
                $fingerprint = hash('sha256', implode('|', [$companyId, $userId, $ruleId, $requestId]));
                $severity = IamValue::string($rule->severity);
                $created = DB::table('iam_sod_violations')->insertOrIgnore(['company_id' => $companyId, 'user_id' => $userId, 'sod_rule_id' => $ruleId, 'access_request_id' => $requestId, 'status' => 'open', 'severity' => $severity, 'fingerprint' => $fingerprint, 'checksum' => IamChecksum::make(['fingerprint' => $fingerprint, 'severity' => $severity]), 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                if ($created === 1) {
                    $payload = ['request_id' => $requestId, 'user_id' => $userId, 'rule_id' => $ruleId, 'severity' => $severity];
                    DB::table('iam_outbox')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'event_type' => 'iam.sod.violation', 'aggregate_type' => 'access_request', 'aggregate_id' => $requestId, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => IamChecksum::make($payload), 'idempotency_key' => (string) Str::uuid(), 'attempts' => 0, 'available_at' => now(), 'created_at' => now()]);
                }
                $blocking = $blocking || $severity === 'blocking';
            }
        }

        return $blocking;
    }
}
