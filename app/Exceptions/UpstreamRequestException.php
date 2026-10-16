<?php

namespace App\Exceptions;

use Exception;

/**
 * TokenomixAI refused the request itself (4xx such as 400 "Request rejected by AI provider").
 * Not safe to resend unchanged; the caller may retry once with a cleaned/smaller payload.
 */
class UpstreamRequestException extends Exception
{
    public function __construct(string $message, public int $status, public string $errorCode = '')
    {
        parent::__construct($message);
    }
}
