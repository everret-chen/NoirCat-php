<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A member's complaint about a post or a comment.
 *
 * Reports are polymorphic so the same queue will hold future content types
 * (book reviews, guild messages) without another table; the moderation actions
 * that follow a report are written to audit_logs, not here.
 *
 * @property int $id
 * @property int|null $reporter_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property ReportReason $reason
 * @property string|null $detail
 * @property ReportStatus $status
 * @property int|null $handled_by
 * @property \Illuminate\Support\Carbon|null $handled_at
 * @property string|null $resolution_note
 */
class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;

    /**
     * Reporting is a member action, so the content is the only thing a client
     * may choose; the reporter always comes from the session.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reason',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Report>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ReportStatus::PENDING->value);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
