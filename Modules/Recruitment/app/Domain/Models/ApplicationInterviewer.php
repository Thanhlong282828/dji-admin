<?php

namespace Modules\Recruitment\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $user_id */
final class ApplicationInterviewer extends Model
{
    protected $fillable = [
        'application_interview_id',
        'company_id',
        'role',
        'user_id',
    ];

    /** @return BelongsTo<ApplicationInterview,$this> */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(ApplicationInterview::class, 'application_interview_id');
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
