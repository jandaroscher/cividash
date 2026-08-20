<?php

namespace App\Exceptions;

use Exception;

class ThemeInUseException extends Exception
{
    public function __construct(string $message = 'Cannot delete a theme that is still assigned to one or more dashboards.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
