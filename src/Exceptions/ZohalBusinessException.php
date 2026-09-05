<?php

declare(strict_types=1);

namespace Zohal\Sdk\Exceptions;

/**
 * Thrown when the API responds 200 OK but the payload reports a business
 * error (response_body.error_code is non-null, e.g. CARD_NOT_FOUND,
 * IBAN_NOT_VALID). The HTTP call succeeded; the inquiry did not.
 */
class ZohalBusinessException extends ZohalException
{
}
