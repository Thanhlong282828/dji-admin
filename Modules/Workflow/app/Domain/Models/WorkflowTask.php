<?php

namespace Modules\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int $workflow_instance_id
 * @property int $workflow_step_id
 * @property int|null $assigned_user_id
 * @property int|null $claimed_by
 * @property string $status
 * @property int $lock_version
 * @property int $escalation_level
 */
final class WorkflowTask extends Model
{
    protected $table = 'workflow_tasks';

    protected $guarded = [];
}
