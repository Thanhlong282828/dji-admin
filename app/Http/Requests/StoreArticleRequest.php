<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:180'], 'excerpt' => ['required', 'string', 'max:500'], 'body' => ['required', 'string', 'min:80'], 'category' => ['required', Rule::in(['Công nghệ', 'Flycam', 'Camera', 'Sáng tạo'])], 'hero_image' => ['required', 'url', 'max:2048'], 'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])], 'featured' => ['sometimes', 'boolean'], 'published_at' => ['nullable', 'date'], 'seo_title' => ['nullable', 'string', 'max:70'], 'seo_description' => ['nullable', 'string', 'max:160']];
    }
}
