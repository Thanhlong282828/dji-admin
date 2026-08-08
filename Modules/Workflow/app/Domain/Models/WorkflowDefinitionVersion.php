<?php

namespace Modules\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $workflow_definition_id @property int $version @property string $routing_type @property string $checksum */
final class WorkflowDefinitionVersion extends Model
{
    protected $table = 'workflow_definition_versions';

    protected $guarded = [];
}
