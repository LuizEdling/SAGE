<?php

namespace App\Exceptions;

class InvalidFunctionParametersException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct(
            errorCode: 'INVALID_FUNCTION_PARAMETERS',
            message: $message,
            status: 422,
        );
    }
}
