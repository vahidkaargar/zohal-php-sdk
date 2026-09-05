<?php

declare(strict_types=1);

namespace Zohal\Sdk\Exceptions;

/**
 * Thrown for transport/HTTP-level failures: non-2xx responses (404, 500,
 * 503, ...) or a request that never reached the API (network error).
 */
class ZohalRequestException extends ZohalException
{
}
