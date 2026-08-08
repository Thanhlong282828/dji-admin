<?php

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $article = $this->resource;
        if (! $article instanceof Article) {
            return [];
        }

        return ['id' => $article->id, 'title' => $article->title, 'slug' => $article->slug, 'excerpt' => $article->excerpt, 'body' => $article->body, 'category' => $article->category, 'hero_image' => $article->hero_image, 'featured' => $article->featured, 'published_at' => $article->published_at?->toAtomString(), 'author' => $this->whenLoaded('author', fn (): array => ['name' => $article->author->name])];
    }
}
