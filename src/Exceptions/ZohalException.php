<?php

declare(strict_types=1);

namespace Zohal\Sdk\Exceptions;

use RuntimeException;

/**
 * Base exception for all Zohal SDK errors. Carries the API's error_code
 * and raw decoded response body so callers can branch on them without
 * re-parsing JSON.
 */
class ZohalException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $errorCode = null,
        private readonly ?int $httpStatus = null,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
