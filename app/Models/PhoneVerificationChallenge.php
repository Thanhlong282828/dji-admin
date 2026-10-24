<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $phone_e164
 * @property string $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property int $max_attempts
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 */
final class PhoneVerificationChallenge extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'phone_e164', 'purpose', 'code_hash', 'attempts', 'max_attempts', 'expires_at', 'consumed_at', 'request_ip_hash'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'max_attempts' => 'integer', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
