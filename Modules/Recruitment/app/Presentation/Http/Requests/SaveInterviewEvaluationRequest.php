<?php

namespace Modules\Recruitment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Recruitment\Domain\Enums\InterviewRecommendation;

final class SaveInterviewEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer'],
            'recommendation' => ['required', Rule::enum(InterviewRecommendation::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.criterion_code' => ['required', 'regex:/^[A-Z0-9_]{2,50}$/'],
            'items.*.criterion_label' => ['required', 'string', 'max:255'],
            'items.*.score' => ['required', 'integer', 'min:0'],
            'items.*.maximum_score' => ['required', 'integer', 'min:1'],
            'items.*.weight' => ['required', 'integer', 'between:1,100'],
            'items.*.comment' => ['nullable', 'string', 'max:2000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'concerns' => ['nullable', 'string', 'max:5000'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
