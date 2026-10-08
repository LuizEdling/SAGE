<?php

namespace App\Exceptions;

class NoActiveExecutionKeyException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            errorCode: 'NO_ACTIVE_EXECUTION_KEY',
            message: 'Nenhuma chave de execução ativa: a execução de funções no sistema legado está suspensa.',
            status: 409,
        );
    }
}
