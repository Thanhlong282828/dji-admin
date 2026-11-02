<?php

namespace Modules\Employee\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Employee\Domain\Enums\DocumentClassification;

final class UploadEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'type' => ['required', Rule::enum(DocumentClassification::class)],
            'name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'max:10240', 'extensions:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp'],
        ];
    }
}
