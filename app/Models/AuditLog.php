<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only record of a security relevant operation.
 *
 * Rows are never updated, hence UPDATED_AT is disabled.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const RESULT_SUCCESS = 'success';

    public const RESULT_FAILURE = 'failure';

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'result',
        'ip',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

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
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
