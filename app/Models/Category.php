<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Forum section or book category, separated by the "type" column.
 */
class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    public const TYPE_POST = 'post';

    public const TYPE_BOOK = 'book';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'slug',
        'name',
        'name_en',
        'description',
        'sort_order',
    ];

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Locale aware display name, falling back to the primary name.
     */
    public function displayName(): string
    {
        if (app()->getLocale() === 'en' && $this->name_en !== null && $this->name_en !== '') {
            return $this->name_en;
        }

        return $this->name;
    }
}
