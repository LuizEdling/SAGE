<?php

namespace App\Exceptions;

class ExecutionExpiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            errorCode: 'EXECUTION_EXPIRED',
            message: 'O prazo para autorizar esta alteração terminou. Faça a pergunta de novo para receber uma nova proposta.',
            status: 409,
        );
    }
}
