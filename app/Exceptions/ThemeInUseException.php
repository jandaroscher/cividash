<?php

namespace App\Exceptions;

use Exception;

class ThemeInUseException extends Exception
{
    public function __construct(?string $message = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message ?? __('filament.resources.theme.in_use_exception_message'), $code, $previous);
    }
}
