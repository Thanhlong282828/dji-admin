<?php

namespace Modules\Employee\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class EmployeeDocumentHistory extends Model
{
    public $timestamps = false;

    protected $table = 'employee_documents_history';

    protected $fillable = [
    ];
}
