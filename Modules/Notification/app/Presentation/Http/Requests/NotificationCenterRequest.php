<?php

namespace Modules\Notification\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class NotificationCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1'], 'code' => ['sometimes', 'required', 'string', 'max:100', 'alpha_dash'], 'name' => ['sometimes', 'required', 'string', 'max:255'], 'category' => ['sometimes', 'required', 'string', 'max:80'], 'subject' => ['sometimes', 'required', 'string', 'max:255'], 'body' => ['sometimes', 'required', 'string', 'max:5000'], 'mandatory' => ['sometimes', 'boolean'], 'database_enabled' => ['sometimes', 'boolean'], 'broadcast_enabled' => ['sometimes', 'boolean'], 'email_enabled' => ['sometimes', 'boolean']];
    }

    public function companyId(): int
    {
        return $this->integer('company_id');
    }

    public function actorId(): int
    {
        $id = $this->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }

    public function text(string $key, string $default = ''): string
    {
        $value = $this->validated($key, $default);

        return is_string($value) ? trim($value) : $default;
    }
}
