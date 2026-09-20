<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;
use RuntimeException;

/**
 * Domain failure raised by services when a request cannot be fulfilled for a
 * business reason. Rendered as { code, message, data } by ApiExceptionRenderer.
 */
class BusinessException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode = ErrorCode::BUSINESS_RULE_VIOLATION,
        ?string $message = null,
        public readonly ?int $status = null,
        public readonly mixed $data = null,
    ) {
        parent::__construct($message ?? $errorCode->message());
    }

    public function httpStatus(): int
    {
        return $this->status ?? $this->errorCode->httpStatus();
    }

    public static function make(
        ErrorCode $errorCode = ErrorCode::BUSINESS_RULE_VIOLATION,
        ?string $message = null,
        mixed $data = null,
    ): self {
        return new self($errorCode, $message, null, $data);
    }
}
