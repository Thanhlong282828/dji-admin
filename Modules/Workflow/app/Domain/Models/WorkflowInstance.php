<?php

namespace Modules\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int $workflow_definition_version_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $definition_snapshot
 * @property string $engine_status
 */
final class WorkflowInstance extends Model
{
    protected $table = 'workflow_instances';

    protected $guarded = [];
}
