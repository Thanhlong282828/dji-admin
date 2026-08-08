<?php

namespace Modules\Workflow\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Models\WorkflowDefinition;
use Modules\Workflow\Domain\Models\WorkflowDefinitionVersion;

final readonly class WorkflowDefinitionService
{
    public function create(WorkflowDefinitionData $data): int
    {
        return DB::transaction(function () use ($data): int {
            $definitionId = (int) DB::table('workflow_definitions')->insertGetId(['company_id' => $data->companyId, 'uuid' => (string) Str::uuid(), 'code' => $data->code, 'name' => $data->name, 'description' => $data->description, 'status' => 'draft', 'latest_version' => 1, 'created_by' => $data->actorId, 'created_at' => now(), 'updated_at' => now()]);
            $configuration = ['routing_type' => $data->routingType, 'steps' => $data->steps];
            $versionId = (int) DB::table('workflow_definition_versions')->insertGetId(['workflow_definition_id' => $definitionId, 'version' => 1, 'routing_type' => $data->routingType, 'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR), 'checksum' => hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data->steps as $index => $step) {
                DB::table('workflow_steps')->insert(['workflow_definition_version_id' => $versionId, 'step_key' => $step['key'], 'name' => $step['name'], 'sequence' => $index + 1, 'approval_mode' => $step['mode'], 'resolver_type' => $step['resolver_type'], 'resolver_value' => $step['resolver_value'], 'condition' => isset($step['condition']) ? json_encode($step['condition'], JSON_THROW_ON_ERROR) : null, 'due_after_minutes' => $step['due_after_minutes'] ?? null, 'escalation_after_minutes' => $step['escalation_after_minutes'] ?? null, 'fallback_resolver_type' => $step['fallback_resolver_type'] ?? null, 'fallback_resolver_value' => $step['fallback_resolver_value'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $definitionId;
        }, 3);
    }

    public function publish(int $companyId, int $definitionId, int $actorId): int
    {
        return DB::transaction(function () use ($companyId, $definitionId, $actorId): int {
            $definition = WorkflowDefinition::query()->where('company_id', $companyId)->whereKey($definitionId)->lockForUpdate()->first();
            abort_if($definition === null, 404);
            $status = $definition->status;
            if ($status === 'published') {
                return $definitionId;
            }
            if ($status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft workflow definitions can be published.']);
            }
            $version = WorkflowDefinitionVersion::query()->where('workflow_definition_id', $definitionId)->where('version', $definition->latest_version)->first();
            abort_if($version === null, 422, 'Definition version missing.');
            if (! DB::table('workflow_steps')->where('workflow_definition_version_id', $version->id)->exists()) {
                throw ValidationException::withMessages(['steps' => 'At least one workflow step is required.']);
            }
            DB::table('workflow_definition_versions')->where('id', $version->id)->update(['published_at' => now(), 'published_by' => $actorId, 'updated_at' => now()]);
            DB::table('workflow_definitions')->where('id', $definitionId)->update(['status' => 'published', 'updated_at' => now()]);

            return $definitionId;
        }, 3);
    }
}
