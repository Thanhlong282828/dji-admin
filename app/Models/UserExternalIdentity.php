<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $user_id @property string $provider @property string $provider_user_id */
final class UserExternalIdentity extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'provider_email', 'provider_email_verified', 'display_name', 'avatar_url', 'linked_at', 'last_used_at'];

    protected function casts(): array
    {
        return ['provider_email_verified' => 'boolean', 'linked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
