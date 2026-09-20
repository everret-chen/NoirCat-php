<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * API error codes used by the { code, message, data } response envelope.
 *
 * Ranges: 1xxx authentication, 2xxx authorization, 3xxx validation/resources,
 * 4xxx business rules, 5xxx system level failures.
 */
enum ErrorCode: int
{
    case SUCCESS = 0;

    // 1xxx authentication
    case UNAUTHENTICATED = 1001;
    case TOKEN_EXPIRED = 1002;
    case INVALID_CREDENTIALS = 1003;
    case ACCOUNT_DISABLED = 1004;
    case EMAIL_NOT_VERIFIED = 1005;

    // 2xxx authorization
    case FORBIDDEN = 2001;
    case INSUFFICIENT_ROLE = 2002;

    // 3xxx validation and resources
    case VALIDATION_FAILED = 3001;
    case RESOURCE_NOT_FOUND = 3002;
    case ROUTE_NOT_FOUND = 3003;
    case METHOD_NOT_ALLOWED = 3004;

    // 4xxx business rules
    case BUSINESS_RULE_VIOLATION = 4001;

    // 5xxx system
    case RATE_LIMITED = 5001;
    case SYSTEM_ERROR = 5002;
    case SERVICE_UNAVAILABLE = 5003;

    /**
     * Default HTTP status paired with the code.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::SUCCESS => 200,
            self::UNAUTHENTICATED, self::TOKEN_EXPIRED, self::INVALID_CREDENTIALS => 401,
            self::ACCOUNT_DISABLED, self::EMAIL_NOT_VERIFIED, self::FORBIDDEN, self::INSUFFICIENT_ROLE => 403,
            self::VALIDATION_FAILED => 422,
            self::RESOURCE_NOT_FOUND, self::ROUTE_NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::BUSINESS_RULE_VIOLATION => 400,
            self::RATE_LIMITED => 429,
            self::SERVICE_UNAVAILABLE => 503,
            self::SYSTEM_ERROR => 500,
        };
    }

    /**
     * Translated message shown to API clients.
     */
    public function message(): string
    {
        return __('api.errors.'.$this->name);
    }

    /**
     * Map an HTTP status to the closest error code, used for framework
     * exceptions that carry only a status.
     */
    public static function fromHttpStatus(int $status): self
    {
        return match (true) {
            $status === 401 => self::UNAUTHENTICATED,
            $status === 403 => self::FORBIDDEN,
            $status === 404 => self::ROUTE_NOT_FOUND,
            $status === 405 => self::METHOD_NOT_ALLOWED,
            $status === 422 => self::VALIDATION_FAILED,
            $status === 429 => self::RATE_LIMITED,
            $status === 503 => self::SERVICE_UNAVAILABLE,
            $status >= 500 => self::SYSTEM_ERROR,
            default => self::BUSINESS_RULE_VIOLATION,
        };
    }
}
