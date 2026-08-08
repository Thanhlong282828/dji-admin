<?php

namespace Modules\Recruitment\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool $consent
 * @property string $uuid
 * @property string $email
 * @property string $full_name
 * @property int $id
 * @property int $company_id
 * @property string|null $phone
 * @property string $status
 * @property array<string, mixed>|null $profile
 * @property int|null $hired_employee_id
 * @property int|null $hired_application_id
 * @property string|null $profile_fingerprint
 * @property string|null $resume_storage_key
 * @property string|null $resume_mime_type
 * @property string|null $resume_scan_status
 * @property int $resume_version
 */
final class Candidate extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    protected $hidden = ['resume_url', 'resume_storage_key'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['profile' => 'array', 'consent' => 'boolean'];
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
