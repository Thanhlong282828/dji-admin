<?php

namespace Modules\Recruitment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Recruitment\Domain\Enums\InterviewType;

final class ScheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'application_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'interview_type' => ['required', Rule::enum(InterviewType::class)],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'timezone'],
            'location' => ['nullable', 'string', 'max:500'],
            'meeting_url' => ['nullable', 'url', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'round_number' => ['required', 'integer', 'min:1', 'max:50'],
            'interviewer_user_ids' => ['required', 'array', 'min:1'],
            'interviewer_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'expected_application_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
