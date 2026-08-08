<?php

namespace Modules\Leave\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LeaveAttachmentRequest extends FormRequest
{
    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $action = (string) $this->route('action');
        $rules = ['company_id' => ['required', 'integer', 'min:1'], 'version' => ['nullable', 'integer', 'min:1'], 'idempotency_key' => ['nullable', 'uuid']];
        if (in_array($action, ['upload', 'replace'], true)) {
            $rules['attachment'] = ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'];
            $rules['idempotency_key'] = ['required', 'uuid'];
        }
        if (in_array($action, ['replace', 'remove'], true)) {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }
}
