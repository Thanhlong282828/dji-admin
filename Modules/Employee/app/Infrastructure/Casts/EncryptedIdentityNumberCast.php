<?php

namespace Modules\Employee\Infrastructure\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Modules\Employee\Domain\Services\EmployeeIdentityService;

/** @implements CastsAttributes<?string, ?string> */
final class EncryptedIdentityNumberCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return app(EmployeeIdentityService::class)->decrypt(is_string($value) ? $value : null);
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) && $value !== '' ? app(EmployeeIdentityService::class)->encrypt($value) : null;
    }
}
