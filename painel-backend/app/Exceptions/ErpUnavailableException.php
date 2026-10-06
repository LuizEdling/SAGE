<?php

namespace App\Exceptions;

class ErpUnavailableException extends DomainException
{
    public function __construct(string $message = 'O sistema legado não respondeu. Tente novamente em instantes.')
    {
        parent::__construct(
            errorCode: 'ERP_UNAVAILABLE',
            message: $message,
            status: 502,
        );
    }
}
