<?php

namespace App\Exceptions;

class ExecutionNotPendingException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            errorCode: 'EXECUTION_NOT_PENDING',
            message: 'Esta alteração já foi decidida ou não está mais disponível.',
            status: 409,
        );
    }
}
