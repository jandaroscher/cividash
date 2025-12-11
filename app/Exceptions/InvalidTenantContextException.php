<?php

namespace App\Exceptions;

use Exception;

class InvalidTenantContextException extends Exception
{
    public function __construct(string $message = 'No tenant context available and no default tenant found. Please provide a tenant explicitly.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
