<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a report.
 *
 * A report is never deleted: "dismissed" (no action needed) and "resolved"
 * (action taken) are both terminal states, which keeps the moderation history
 * auditable.
 */
enum ReportStatus: string
{
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function label(): string
    {
        return __('forum.report_statuses.'.$this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::PENDING;
    }
}
