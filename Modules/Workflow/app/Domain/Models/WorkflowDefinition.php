<?php

namespace Modules\Workflow\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $company_id @property string $code @property string $status @property int $latest_version */
final class WorkflowDefinition extends Model
{
    protected $table = 'workflow_definitions';

    protected $guarded = [];
}
