<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $author_id
 * @property string $title
 * @property string $slug
 * @property string $excerpt
 * @property string $body
 * @property string $category
 * @property string $hero_image
 * @property bool $featured
 * @property Carbon|null $published_at
 * @property User $author
 */
final class Article extends Model
{
    /** @use HasFactory<Factory<Article>> */
    use HasFactory,SoftDeletes;

    protected $fillable = ['author_id', 'title', 'slug', 'excerpt', 'body', 'category', 'hero_image', 'status', 'featured', 'published_at', 'seo_title', 'seo_description'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['featured' => 'boolean', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    protected static function booted(): void
    {
        self::creating(function (Article $article): void {
            $article->slug = $article->slug ?: Str::slug($article->title).'-'.Str::lower(Str::random(5));
        });
    }
}
