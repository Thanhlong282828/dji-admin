<?php

namespace Modules\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workflow_definition_version_id
 * @property int $sequence
 * @property string $approval_mode
 * @property string $resolver_type
 * @property string $resolver_value
 * @property string|null $condition
 * @property int|null $due_after_minutes
 * @property int|null $escalation_after_minutes
 * @property string|null $fallback_resolver_type
 * @property string|null $fallback_resolver_value
 */
final class WorkflowStep extends Model
{
    protected $table = 'workflow_steps';

    protected $guarded = [];
}
