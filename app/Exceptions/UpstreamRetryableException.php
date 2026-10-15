<?php

namespace App\Exceptions;

use Exception;

/**
 * A TokenomixAI failure that is safe to retry (502 upstream_unavailable, 429 rate limit,
 * dropped connection), per the gateway docs.
 */
class UpstreamRetryableException extends Exception
{
    public function __construct(string $message, public ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }
}
