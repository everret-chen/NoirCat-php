<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a member reported a piece of content.
 *
 * The list is deliberately short: a long taxonomy turns reporting into a chore.
 * "other" pairs with the free text field.
 */
enum ReportReason: string
{
    case SPAM = 'spam';
    case ABUSE = 'abuse';
    case ILLEGAL = 'illegal';
    case OFF_TOPIC = 'off_topic';
    case OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }

    public function label(): string
    {
        return __('forum.report_reasons.'.$this->value);
    }
}
