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
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'title' => ['required', 'string', 'max:180'], 'excerpt' => ['required', 'string', 'max:500'], 'body' => ['required', 'string', 'min:80'], 'category' => ['required', Rule::in(['Công nghệ', 'Flycam', 'Camera', 'Sáng tạo'])], 'cover_image' => [$this->route('article') ? 'nullable' : 'required_without:hero_image', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:15360', 'dimensions:max_width=8192,max_height=8192'], 'hero_image' => ['nullable', 'url', 'max:2048'], 'gallery_images' => ['nullable', 'array', 'max:12'], 'gallery_images.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:15360', 'dimensions:max_width=8192,max_height=8192'], 'remove_media' => ['nullable', 'array'], 'remove_media.*' => ['integer', 'exists:media_assets,id'], 'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])], 'featured' => ['sometimes', 'boolean'], 'published_at' => ['nullable', 'date'], 'seo_title' => ['nullable', 'string', 'max:70'], 'seo_description' => ['nullable', 'string', 'max:160']];
    }

    /** @return array{title:string,excerpt:string,body:string,category:string,hero_image:?string,status:string,published_at:?string,seo_title:?string,seo_description:?string} */
    public function content(): array
    {
        return [
            'title' => $this->string('title')->toString(),
            'excerpt' => $this->string('excerpt')->toString(),
            'body' => $this->string('body')->toString(),
            'category' => $this->string('category')->toString(),
            'hero_image' => $this->nullableString('hero_image'),
            'status' => $this->string('status')->toString(),
            'published_at' => $this->nullableString('published_at'),
            'seo_title' => $this->nullableString('seo_title'),
            'seo_description' => $this->nullableString('seo_description'),
        ];
    }

    /** @return list<int> */
    public function removedMediaIds(): array
    {
        $input = $this->input('remove_media', []);
        if (! is_array($input)) {
            return [];
        }
        $ids = [];
        foreach ($input as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
