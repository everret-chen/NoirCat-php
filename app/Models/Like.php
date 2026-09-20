<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic like for posts and comments.
 */
class Like extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'likeable_type',
        'likeable_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }
}
