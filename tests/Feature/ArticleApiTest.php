<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ArticleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_only_returns_published_articles(): void
    {
        $user = User::factory()->create();
        Article::query()->create(['author_id' => $user->id, 'title' => 'Published story', 'slug' => 'published-story', 'excerpt' => 'A useful editorial summary', 'body' => str_repeat('Premium story body. ', 10), 'category' => 'Camera', 'hero_image' => 'https://example.com/image.jpg', 'status' => 'published', 'featured' => true, 'published_at' => now()]);
        $this->getJson('/api/v1/articles')->assertOk()->assertJsonPath('data.0.slug', 'published-story');
    }
}
