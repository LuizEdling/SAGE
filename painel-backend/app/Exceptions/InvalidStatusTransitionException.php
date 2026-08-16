<?php

namespace App\Exceptions;

class InvalidStatusTransitionException extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            errorCode: 'INVALID_STATUS_TRANSITION',
            message: "Não é possível transicionar de \"{$from}\" para \"{$to}\".",
            status: 422,
        );
    }
}
